<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Pimcore\Model\DataObject;

/**
 * @Route("/api/fhir", name="fhir_api_")
 */
class FhirController extends AbstractController
{
    /**
     * @Route("/metadata", name="metadata", methods={"GET"})
     */
    public function metadata(): JsonResponse
    {
        return $this->json([
            'resourceType' => 'CapabilityStatement',
            'status' => 'active',
            'date' => (new \DateTime())->format('c'),
            'kind' => 'instance',
            'software' => [
                'name' => 'Pimcore FHIR Server',
                'version' => '1.0.0'
            ],
            'fhirVersion' => '4.0.1',
            'format' => ['json'],
            'rest' => [[
                'mode' => 'server',
                'resource' => [
                    ['type' => 'Patient'],
                    ['type' => 'Practitioner'],
                    ['type' => 'Observation'],
                    ['type' => 'Organization']
                ]
            ]]
        ]);
    }

    /**
     * @Route("/{resourceType}", name="list", methods={"GET"})
     */
    public function list(string $resourceType, Request $request): JsonResponse
    {
        $className = $this->getClassName($resourceType);
        if (!$className) {
            return $this->createOperationOutcome('not-found', "Resource type '$resourceType' not supported", 404);
        }

        try {
            $list = $className::getList([
                'limit' => min((int) $request->get('_count', 20), 100),
                'offset' => (int) $request->get('_offset', 0)
            ]);

            $bundle = [
                'resourceType' => 'Bundle',
                'type' => 'searchset',
                'total' => $list->getTotalCount(),
                'entry' => []
            ];

            foreach ($list as $object) {
                if (method_exists($object, 'toFhir')) {
                    $bundle['entry'][] = [
                        'fullUrl' => $request->getSchemeAndHttpHost() . '/api/fhir/' . $resourceType . '/' . $object->getId(),
                        'resource' => $object->toFhir()
                    ];
                }
            }

            return $this->json($bundle);
        } catch (\Exception $e) {
            return $this->createOperationOutcome('exception', $e->getMessage(), 500);
        }
    }

    private function getClassName(string $resourceType): ?string
    {
        $map = [
            'Patient' => DataObject\Patient::class,
            'Practitioner' => DataObject\Practitioner::class,
            'Observation' => DataObject\Observation::class,
            'Organization' => DataObject\Organization::class
        ];

        return $map[$resourceType] ?? null;
    }

    private function createOperationOutcome(string $code, string $diagnostics, int $status): JsonResponse
    {
        return $this->json([
            'resourceType' => 'OperationOutcome',
            'issue' => [[
                'severity' => 'error',
                'code' => $code,
                'diagnostics' => $diagnostics
            ]]
        ], $status);
    }
}
