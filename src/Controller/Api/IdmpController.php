<?php
// src/Controller/Api/IdmpController.php
namespace App\Controller\Api;

use App\Model\DataObject\MedicinalProduct;
use App\Model\DataObject\Substance;
use Pimcore\Model\DataObject;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api/fhir")
 */
class IdmpController extends AbstractController
{
    /**
     * @Route("/MedicinalProduct", name="api_medicinal_product_search", methods={"GET"})
     */
    public function searchMedicinalProducts(Request $request): JsonResponse
    {
        $bundle = [
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 0,
            'entry' => []
        ];

        $listing = MedicinalProduct::getList();
        
        // Recherche par code ATC
        if ($atcCode = $request->query->get('classification')) {
            $listing->addConditionParam('atcCode = ?', [$atcCode]);
        }

        // Recherche par DCI (INN)
        if ($inn = $request->query->get('inn')) {
            $listing->addConditionParam('nonproprietaryName LIKE ?', ['%' . $inn . '%']);
        }

        // Recherche par MPID
        if ($mpid = $request->query->get('identifier')) {
            $listing->addConditionParam('mpid = ?', [$mpid]);
        }

        // Recherche par nom
        if ($name = $request->query->get('name')) {
            $listing->addConditionParam('(name LIKE ? OR nonproprietaryName LIKE ?)', ['%' . $name . '%', '%' . $name . '%']);
        }

        // Recherche par type
        if ($type = $request->query->get('type')) {
            $listing->addConditionParam('productType = ?', [$type]);
        }

        // Recherche par statut légal
        if ($status = $request->query->get('legal-status')) {
            $listing->addConditionParam('legalStatusOfSupply = ?', [$status]);
        }

        // Pagination
        $count = $request->query->get('_count', 10);
        $offset = $request->query->get('_offset', 0);
        $listing->setLimit($count);
        $listing->setOffset($offset);

        $products = $listing->load();
        $bundle['total'] = $listing->getTotalCount();

        foreach ($products as $product) {
            $bundle['entry'][] = [
                'fullUrl' => $request->getSchemeAndHttpHost() . '/api/fhir/MedicinalProduct/' . $product->getId(),
                'resource' => $product->toFhirResource()
            ];
        }

        return new JsonResponse($bundle);
    }

    /**
     * @Route("/MedicinalProduct/{id}", name="api_medicinal_product_read", methods={"GET"})
     */
    public function getMedicinalProduct(int $id): JsonResponse
    {
        $product = MedicinalProduct::getById($id);
        
        if (!$product) {
            return new JsonResponse([
                'resourceType' => 'OperationOutcome',
                'issue' => [[
                    'severity' => 'error',
                    'code' => 'not-found',
                    'diagnostics' => 'MedicinalProduct not found'
                ]]
            ], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($product->toFhirResource());
    }

    /**
     * @Route("/MedicinalProduct", name="api_medicinal_product_create", methods={"POST"})
     */
    public function createMedicinalProduct(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if ($data['resourceType'] !== 'MedicinalProduct') {
            return new JsonResponse([
                'resourceType' => 'OperationOutcome',
                'issue' => [[
                    'severity' => 'error',
                    'code' => 'invalid',
                    'diagnostics' => 'Invalid resource type'
                ]]
            ], Response::HTTP_BAD_REQUEST);
        }

        $product = new MedicinalProduct();
        $product->setParent(DataObject::getByPath('/IDMP/MedicinalProducts'));
        $product->fromFhirResource($data);
        
        // Générer une clé unique
        $product->setKey('mp-' . uniqid());
        
        try {
            $product->save();
            
            return new JsonResponse(
                $product->toFhirResource(),
                Response::HTTP_CREATED,
                ['Location' => '/api/fhir/MedicinalProduct/' . $product->getId()]
            );
        } catch (\Exception $e) {
            return new JsonResponse([
                'resourceType' => 'OperationOutcome',
                'issue' => [[
                    'severity' => 'error',
                    'code' => 'exception',
                    'diagnostics' => $e->getMessage()
                ]]
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @Route("/Substance", name="api_substance_search", methods={"GET"})
     */
    public function searchSubstances(Request $request): JsonResponse
    {
        $bundle = [
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => 0,
            'entry' => []
        ];

        $listing = Substance::getList();
        
        // Recherche par identifiant
        if ($identifier = $request->query->get('identifier')) {
            $listing->addConditionParam('identifier = ?', [$identifier]);
        }

        // Recherche par numéro CAS
        if ($cas = $request->query->get('cas')) {
            $listing->addConditionParam('casNumber = ?', [$cas]);
        }

        // Recherche par INN
        if ($inn = $request->query->get('inn')) {
            $listing->addConditionParam('inn = ?', [$inn]);
        }

        // Recherche par nom
        if ($name = $request->query->get('name')) {
            $listing->addConditionParam('substanceName LIKE ?', ['%' . $name . '%']);
        }

        // Recherche par type
        if ($type = $request->query->get('category')) {
            $listing->addConditionParam('substanceType = ?', [$type]);
        }

        // Pagination
        $count = $request->query->get('_count', 10);
        $offset = $request->query->get('_offset', 0);
        $listing->setLimit($count);
        $listing->setOffset($offset);

        $substances = $listing->load();
        $bundle['total'] = $listing->getTotalCount();

        foreach ($substances as $substance) {
            $bundle['entry'][] = [
                'fullUrl' => $request->getSchemeAndHttpHost() . '/api/fhir/Substance/' . $substance->getId(),
                'resource' => $substance->toFhirResource()
            ];
        }

        return new JsonResponse($bundle);
    }

    /**
     * @Route("/Substance/{id}", name="api_substance_read", methods={"GET"})
     */
    public function getSubstance(int $id): JsonResponse
    {
        $substance = Substance::getById($id);
        
        if (!$substance) {
            return new JsonResponse([
                'resourceType' => 'OperationOutcome',
                'issue' => [[
                    'severity' => 'error',
                    'code' => 'not-found',
                    'diagnostics' => 'Substance not found'
                ]]
            ], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($substance->toFhirResource());
    }

    /**
     * @Route("/MedicinalProduct/$lookup", name="api_medicinal_product_lookup", methods={"GET", "POST"})
     */
    public function lookupMedicinalProduct(Request $request): JsonResponse
    {
        $parameters = $request->query->all();
        if ($request->getMethod() === 'POST') {
            $parameters = array_merge($parameters, json_decode($request->getContent(), true) ?: []);
        }

        $results = [];

        // Recherche par code ATC
        if (isset($parameters['code'])) {
            $product = MedicinalProduct::findByAtcCode($parameters['code']);
            if ($product) {
                $results[] = $product->toFhirResource();
            }
        }

        // Recherche par MPID
        if (isset($parameters['system']) && $parameters['system'] === 'urn:oid:2.16.840.1.113883.3.1937') {
            if (isset($parameters['code'])) {
                $product = MedicinalProduct::findByMpid($parameters['code']);
                if ($product) {
                    $results[] = $product->toFhirResource();
                }
            }
        }

        if (empty($results)) {
            return new JsonResponse([
                'resourceType' => 'OperationOutcome',
                'issue' => [[
                    'severity' => 'information',
                    'code' => 'not-found',
                    'diagnostics' => 'No medicinal product found for the given parameters'
                ]]
            ]);
        }

        return new JsonResponse($results[0]);
    }

    /**
     * @Route("/$idmp-transform", name="api_idmp_transform", methods={"POST"})
     */
    public function transformToIdmp(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['resourceType'])) {
            return new JsonResponse([
                'resourceType' => 'OperationOutcome',
                'issue' => [[
                    'severity' => 'error',
                    'code' => 'invalid',
                    'diagnostics' => 'Missing resourceType'
                ]]
            ], Response::HTTP_BAD_REQUEST);
        }

        // Transformation de Medication vers MedicinalProduct
        if ($data['resourceType'] === 'Medication') {
            $medicinalProduct = $this->transformMedicationToMedicinalProduct($data);
            return new JsonResponse($medicinalProduct);
        }

        return new JsonResponse([
            'resourceType' => 'OperationOutcome',
            'issue' => [[
                'severity' => 'error',
                'code' => 'not-supported',
                'diagnostics' => 'Transformation not supported for ' . $data['resourceType']
            ]]
        ], Response::HTTP_BAD_REQUEST);
    }

    private function transformMedicationToMedicinalProduct(array $medication): array
    {
        $medicinalProduct = [
            'resourceType' => 'MedicinalProduct',
            'id' => $medication['id'] ?? null,
            'identifier' => $medication['identifier'] ?? []
        ];

        // Transformer le code en classification ATC
        if (isset($medication['code']['coding'])) {
            foreach ($medication['code']['coding'] as $coding) {
                if ($coding['system'] === 'http://www.whocc.no/atc') {
                    $medicinalProduct['classification'] = [[
                        'coding' => [$coding]
                    ]];
                }
            }
        }

        // Transformer le nom
        if (isset($medication['code']['text'])) {
            $medicinalProduct['name'] = [[
                'productName' => $medication['code']['text']
            ]];
        }

        // Transformer les ingrédients
        if (isset($medication['ingredient'])) {
            $medicinalProduct['ingredient'] = [];
            foreach ($medication['ingredient'] as $ingredient) {
                if (isset($ingredient['itemReference'])) {
                    $medicinalProduct['ingredient'][] = [
                        'itemReference' => $ingredient['itemReference']
                    ];
                }
            }
        }

        // Transformer la forme
        if (isset($medication['form'])) {
            $medicinalProduct['combinedPharmaceuticalDoseForm'] = $medication['form'];
        }

        return $medicinalProduct;
    }
}