<?php
// src/Model/DataObject/MedicinalProduct.php (version adaptée)
namespace App\Model\DataObject;

use App\Traits\FhirResourceTrait;
use Pimcore\Model\DataObject\MedicinalProduct as BaseMedicinalProduct;

class MedicinalProduct extends BaseMedicinalProduct
{
    use FhirResourceTrait;

    /**
     * Helper pour définir un nom simple (rétrocompatibilité)
     */
    public function setSimpleName(string $name): self
    {
        // Créer un MedicinalProductName
        $productName = new \Pimcore\Model\DataObject\MedicinalProductName();
        $productName->setKey('name-' . md5($name . time()));
        $productName->setParent(\Pimcore\Model\DataObject::getByPath('/IDMP/MedicinalProductNames'));
        $productName->setProductName($name);
        $productName->setPublished(true);
        $productName->save();
        
        $this->setName([$productName]);
        return $this;
    }

    /**
     * Helper pour récupérer le nom principal
     */
    public function getMainName(): ?string
    {
        $names = $this->getName();
        if (is_array($names) && count($names) > 0) {
            return $names[0]->getProductName();
        }
        return null;
    }

    /**
     * Override toFhirResource pour gérer le nouveau format
     */
    public function toFhirResource(): array
    {
        $resource = [
            'resourceType' => 'MedicinalProduct',
            'id' => $this->getId(),
            'meta' => $this->getFhirMeta()
        ];

        // Identifiants
        if ($this->getIdentifier()) {
            $resource['identifier'] = [];
            foreach ($this->getIdentifier() as $identifier) {
                $resource['identifier'][] = [
                    'system' => $identifier->getSystem(),
                    'value' => $identifier->getIdentifierValue(),
                    'use' => $identifier->getUse()
                ];
            }
        }

        // Noms
        if ($this->getName()) {
            $resource['name'] = [];
            foreach ($this->getName() as $name) {
                $nameData = [
                    'productName' => $name->getProductName()
                ];
                
                if ($name->getNameType()) {
                    $nameData['type'] = [
                        'coding' => [[
                            'code' => $name->getNameType()->getCodings()[0]->getCode(),
                            'display' => $name->getNameType()->getText()
                        ]]
                    ];
                }
                
                $resource['name'][] = $nameData;
            }
        }

        // Type de produit
        if ($this->getMedicinalProductType()) {
            $resource['type'] = $this->codeableConceptToFhir($this->getMedicinalProductType());
        }

        // Domaine
        if ($this->getDomain()) {
            $resource['domain'] = $this->codeableConceptToFhir($this->getDomain());
        }

        // Classifications (ATC)
        if ($this->getClassification()) {
            $resource['classification'] = [];
            foreach ($this->getClassification() as $classification) {
                $resource['classification'][] = $this->codeableConceptToFhir($classification);
            }
        }

        return $resource;
    }

    private function codeableConceptToFhir($concept): array
    {
        $result = [];
        
        if ($concept->getText()) {
            $result['text'] = $concept->getText();
        }
        
        if ($concept->getCodings()) {
            $result['coding'] = [];
            foreach ($concept->getCodings() as $coding) {
                $result['coding'][] = [
                    'system' => $coding->getSystem(),
                    'code' => $coding->getCode(),
                    'display' => $coding->getDisplay()
                ];
            }
        }
        
        return $result;
    }
}