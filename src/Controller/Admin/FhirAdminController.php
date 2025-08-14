<?php
namespace App\Controller\Admin;

use Pimcore\Bundle\AdminBundle\Controller\AdminController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/fhir", name="admin_fhir_")
 */
class FhirAdminController extends AdminController
{
    /**
     * @Route("/stats", name="stats")
     */
    public function stats(): JsonResponse
    {
        $this->checkPermission('fhir_admin');

        return $this->json([
            'patients' => \Pimcore\Model\DataObject\Patient::getList()->getTotalCount(),
            'practitioners' => \Pimcore\Model\DataObject\Practitioner::getList()->getTotalCount(),
            'observations' => \Pimcore\Model\DataObject\Observation::getList()->getTotalCount()
        ]);
    }
}