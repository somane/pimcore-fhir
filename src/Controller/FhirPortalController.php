<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Pimcore\Model\DataObject\Patient;

class FhirPortalController extends AbstractController
{
    public function index(): Response
    {
        return $this->render('fhir/portal/index.html.twig');
    }

    public function patientDetail(int $id): Response
    {
        $patient = Patient::getById($id);
        
        if (!$patient || !$patient->isPublished()) {
            throw $this->createNotFoundException('Patient non trouvé');
        }

        return $this->render('fhir/portal/patient-detail.html.twig', [
            'patient' => $patient
        ]);
    }
}
