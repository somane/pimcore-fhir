<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Pimcore\Model\DataObject;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Controller Admin FHIR qui hérite de AbstractController au lieu de AdminController
 */
class FhirAdminController extends AbstractController
{
    /**
     * Vérifie les permissions de l'utilisateur
     */
    private function checkAdminPermission(string $permission): void
    {
        $user = \Pimcore\Tool\Admin::getCurrentUser();
        
        if (!$user) {
            throw new AccessDeniedException('User not logged in');
        }
        
        if (!$user->isAllowed($permission)) {
            throw new AccessDeniedException('Permission denied for: ' . $permission);
        }
    }

    /**
     * Retourne les statistiques FHIR
     */
    public function stats(): JsonResponse
    {
        try {
            // Vérifier que l'utilisateur est connecté et a les permissions
            $this->checkAdminPermission('objects');
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Access denied: ' . $e->getMessage()
            ], 403);
        }

        $stats = [
            'success' => true,
            'data' => [
                'patients' => 0,
                'practitioners' => 0,
                'observations' => 0,
                'organizations' => 0
            ]
        ];

        try {
            // Compter les patients
            $patientClass = '\\Pimcore\\Model\\DataObject\\Patient';
            if (class_exists($patientClass)) {
                $list = new \Pimcore\Model\DataObject\Patient\Listing();
                $stats['data']['patients'] = $list->getTotalCount();
            }

            // Compter les praticiens
            $practitionerClass = '\\Pimcore\\Model\\DataObject\\Practitioner';
            if (class_exists($practitionerClass)) {
                $list = new \Pimcore\Model\DataObject\Practitioner\Listing();
                $stats['data']['practitioners'] = $list->getTotalCount();
            }

            // Compter les observations
            $observationClass = '\\Pimcore\\Model\\DataObject\\Observation';
            if (class_exists($observationClass)) {
                $list = new \Pimcore\Model\DataObject\Observation\Listing();
                $stats['data']['observations'] = $list->getTotalCount();
            }

            // Compter les organisations
            $organizationClass = '\\Pimcore\\Model\\DataObject\\Organization';
            if (class_exists($organizationClass)) {
                $list = new \Pimcore\Model\DataObject\Organization\Listing();
                $stats['data']['organizations'] = $list->getTotalCount();
            }
        } catch (\Exception $e) {
            $stats['success'] = false;
            $stats['message'] = 'Erreur lors du calcul des statistiques : ' . $e->getMessage();
            
            // Log l'erreur pour le debug
            \Pimcore\Log\Simple::log('fhir', 'Error in stats: ' . $e->getMessage());
        }

        return $this->json($stats);
    }

    /**
     * Import de données FHIR
     */
    public function import(Request $request): JsonResponse
    {
        try {
            $this->checkAdminPermission('objects');
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        // TODO: Implémenter la logique d'import
        return $this->json([
            'success' => true,
            'message' => 'Fonctionnalité d\'import à implémenter',
            'info' => 'POST un fichier JSON contenant un Bundle FHIR'
        ]);
    }

    /**
     * Validation de ressources FHIR
     */
    public function validate(Request $request): JsonResponse
    {
        try {
            $this->checkAdminPermission('objects');
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid JSON'
            ], 400);
        }

        // Validation basique
        $errors = [];
        
        if (empty($data['resourceType'])) {
            $errors[] = 'resourceType is required';
        }

        return $this->json([
            'success' => empty($errors),
            'errors' => $errors
        ]);
    }
}