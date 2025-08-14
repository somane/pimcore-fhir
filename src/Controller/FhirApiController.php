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
class FhirApiController extends AbstractController
{
    /**
     * @Route("/{resourceType}", name="list", methods={"GET"})
     */
    public function list(string $resourceType, Request $request): JsonResponse
    {
        $className = $this->getClassName($resourceType);
        if (!$className) {
            return $this->json(['error' => 'Resource type not found'], 404);
        }

        $list = $className::getList([
            'limit' => $request->get('_count', 20),
            'offset' => $request->get('_offset', 0)
        ]);

        $bundle = [
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => $list->getTotalCount(),
            'entry' => []
        ];

        foreach ($list as $object) {
            $bundle['entry'][] = [
                'resource' => $object->toFhir()
            ];
        }

        return $this->json($bundle);
    }

    /**
     * @Route("/{resourceType}/{id}", name="read", methods={"GET"})
     */
    public function read(string $resourceType, int $id): JsonResponse
    {
        $className = $this->getClassName($resourceType);
        if (!$className) {
            return $this->json(['error' => 'Resource type not found'], 404);
        }

        $object = $className::getById($id);
        if (!$object || !$object->isPublished()) {
            return $this->json(['error' => 'Resource not found'], 404);
        }

        return $this->json($object->toFhir());
    }

    /**
     * @Route("/{resourceType}", name="create", methods={"POST"})
     */
    public function create(string $resourceType, Request $request): JsonResponse
    {
        $className = $this->getClassName($resourceType);
        if (!$className) {
            return $this->json(['error' => 'Resource type not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        
        // Création de l'objet
        $object = new $className();
        $object->setParentId(1); // À adapter
        $object->setKey(uniqid($resourceType . '-'));
        
        // Mapping des données
        $this->mapFhirData($object, $data);
        $object->save();

        return $this->json($object->toFhir(), 201);
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

    private function mapFhirData($object, array $data): void
    {
        // Logique de mapping selon le type
        if ($object instanceof DataObject\Patient) {
            $object->setIdentifier($data['identifier'][0]['value'] ?? '');
            $object->setFamilyName($data['name'][0]['family'] ?? '');
            $object->setGivenName($data['name'][0]['given'][0] ?? '');
            $object->setGender($data['gender'] ?? null);
            if (!empty($data['birthDate'])) {
                $object->setBirthDate(new \DateTime($data['birthDate']));
            }
        }
        // Ajouter les mappings pour les autres types...
    }
}