<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/fhir", name="fhir_frontend_")
 */
class FhirFrontendController extends AbstractController
{
    /**
     * @Route("/", name="index")
     */
    public function index(): Response
    {
        return $this->render('fhir/index.html.twig');
    }

    /**
     * @Route("/patient/{id}", name="patient_detail")
     */
    public function patientDetail(int $id): Response
    {
        $patient = \Pimcore\Model\DataObject\Patient::getById($id);
        
        if (!$patient || !$patient->isPublished()) {
            throw $this->createNotFoundException();
        }

        return $this->render('fhir/patient-detail.html.twig', [
            'patient' => $patient
        ]);
    }
}