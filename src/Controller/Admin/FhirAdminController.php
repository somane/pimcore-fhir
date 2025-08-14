<?php

namespace App\Controller\Admin;

use Pimcore\Bundle\AdminBundle\Controller\AdminController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Pimcore\Model\DataObject;

class FhirAdminController extends AdminController
{
    public function stats(): JsonResponse
    {
        $this->checkPermission('objects');

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
            if (class_exists('\\Pimcore\\Model\\DataObject\\Patient')) {
                $stats['data']['patients'] = DataObject\Patient::getList()->getTotalCount();
            }

            if (class_exists('\\Pimcore\\Model\\DataObject\\Practitioner')) {
                $stats['data']['practitioners'] = DataObject\Practitioner::getList()->getTotalCount();
            }

            if (class_exists('\\Pimcore\\Model\\DataObject\\Observation')) {
                $stats['data']['observations'] = DataObject\Observation::getList()->getTotalCount();
            }

            if (class_exists('\\Pimcore\\Model\\DataObject\\Organization')) {
                $stats['data']['organizations'] = DataObject\Organization::getList()->getTotalCount();
            }
        } catch (\Exception $e) {
            $stats['success'] = false;
            $stats['message'] = $e->getMessage();
        }

        return $this->json($stats);
    }

    public function import(Request $request): JsonResponse
    {
        $this->checkPermission('objects');

        return $this->json([
            'success' => true,
            'message' => 'Import functionality to be implemented'
        ]);
    }
}