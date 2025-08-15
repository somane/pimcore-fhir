<?php
// src/Model/DataObject/Substance.php
namespace App\Model\DataObject;

use App\Traits\FhirResourceTrait;
use Pimcore\Model\DataObject\Substance as BaseSubstance;

class Substance extends BaseSubstance
{
    use FhirResourceTrait;

    /**
     * Convertit l'objet Pimcore en ressource FHIR Substance
     */
    public function toFhirResource(): array
    {
        $resource = [
            'resourceType' => 'Substance',
            'id' => $this->getIdentifier() ?: $this->getId(),
            'meta' => $this->getFhirMeta(),
            'identifier' => []
        ];

        // Identifiant substance
        if ($this->getIdentifier()) {
            $resource['identifier'][] = [
                'system' => 'urn:oid:2.16.840.1.113883.4.9', // FDA UNII
                'value' => $this->getIdentifier(),
                'use' => 'official'
            ];
        }

        // Numéro CAS
        if ($this->getCasNumber()) {
            $resource['identifier'][] = [
                'system' => 'http://fdasis.nlm.nih.gov',
                'value' => $this->getCasNumber(),
                'use' => 'secondary'
            ];
        }

        // Code et nom de la substance
        $resource['code'] = [
            'coding' => []
        ];

        // DCI (International Nonproprietary Name)
        if ($this->getInn()) {
            $resource['code']['coding'][] = [
                'system' => 'http://www.who.int/medicines/publications/druginformation',
                'code' => $this->getInn(),
                'display' => $this->getSubstanceName()
            ];
        }

        // Nom de la substance
        if ($this->getSubstanceName()) {
            $resource['code']['text'] = $this->getSubstanceName();
        }

        // Type de substance
        if ($this->getSubstanceType()) {
            $resource['category'] = [[
                'coding' => [[
                    'system' => 'http://hl7.org/fhir/substance-category',
                    'code' => $this->getSubstanceType(),
                    'display' => $this->getSubstanceTypeDisplay()
                ]]
            ]];
        }

        // Formule moléculaire
        if ($this->getMolecularFormula()) {
            if (!isset($resource['molecule'])) {
                $resource['molecule'] = [];
            }
            $resource['molecule']['molecularFormula'] = $this->getMolecularFormula();
        }

        // Poids moléculaire
        if ($this->getMolecularWeight()) {
            if (!isset($resource['molecule'])) {
                $resource['molecule'] = [];
            }
            $resource['molecule']['molecularWeight'] = [
                'amount' => [
                    'value' => $this->getMolecularWeight(),
                    'unit' => 'g/mol',
                    'system' => 'http://unitsofmeasure.org',
                    'code' => 'g/mol'
                ]
            ];
        }

        // Structure chimique (SMILES ou InChI)
        if ($this->getStructure()) {
            if (!isset($resource['molecule'])) {
                $resource['molecule'] = [];
            }
            // Déterminer si c'est SMILES ou InChI
            if (strpos($this->getStructure(), 'InChI=') === 0) {
                $resource['molecule']['structuralRepresentation'] = [[
                    'type' => [
                        'coding' => [[
                            'system' => 'http://hl7.org/fhir/substance-representation-type',
                            'code' => 'InChI',
                            'display' => 'InChI'
                        ]]
                    ],
                    'representation' => $this->getStructure()
                ]];
            } else {
                $resource['molecule']['structuralRepresentation'] = [[
                    'type' => [
                        'coding' => [[
                            'system' => 'http://hl7.org/fhir/substance-representation-type',
                            'code' => 'SMILES',
                            'display' => 'SMILES'
                        ]]
                    ],
                    'representation' => $this->getStructure()
                ]];
            }
        }

        // Instance avec concentration/dosage
        if ($this->getStrengthValue()) {
            $resource['instance'] = [[
                'identifier' => [
                    'system' => 'http://example.org/identifiers/substance-instance',
                    'value' => $this->getId() . '-instance'
                ],
                'quantity' => [
                    'value' => $this->getStrengthValue(),
                    'unit' => $this->getStrengthUnit(),
                    'system' => 'http://unitsofmeasure.org',
                    'code' => $this->getStrengthUnitCode()
                ]
            ]];
        }

        return $resource;
    }

    /**
     * Importe les données depuis une ressource FHIR
     */
    public function fromFhirResource(array $resource): self
    {
        // Identifiants
        if (isset($resource['identifier'])) {
            foreach ($resource['identifier'] as $identifier) {
                if ($identifier['system'] === 'urn:oid:2.16.840.1.113883.4.9') {
                    $this->setIdentifier($identifier['value']);
                } elseif ($identifier['system'] === 'http://fdasis.nlm.nih.gov') {
                    $this->setCasNumber($identifier['value']);
                }
            }
        }

        // Code et nom
        if (isset($resource['code'])) {
            if (isset($resource['code']['text'])) {
                $this->setSubstanceName($resource['code']['text']);
            }
            
            if (isset($resource['code']['coding'])) {
                foreach ($resource['code']['coding'] as $coding) {
                    if ($coding['system'] === 'http://www.who.int/medicines/publications/druginformation') {
                        $this->setInn($coding['code']);
                    }
                }
            }
        }

        // Type de substance
        if (isset($resource['category'][0]['coding'][0]['code'])) {
            $this->setSubstanceType($resource['category'][0]['coding'][0]['code']);
        }

        // Molécule
        if (isset($resource['molecule'])) {
            if (isset($resource['molecule']['molecularFormula'])) {
                $this->setMolecularFormula($resource['molecule']['molecularFormula']);
            }
            
            if (isset($resource['molecule']['molecularWeight']['amount']['value'])) {
                $this->setMolecularWeight($resource['molecule']['molecularWeight']['amount']['value']);
            }
            
            if (isset($resource['molecule']['structuralRepresentation'][0]['representation'])) {
                $this->setStructure($resource['molecule']['structuralRepresentation'][0]['representation']);
            }
        }

        // Instance avec dosage
        if (isset($resource['instance'][0]['quantity'])) {
            $quantity = $resource['instance'][0]['quantity'];
            if (isset($quantity['value'])) {
                $this->setStrengthValue($quantity['value']);
            }
            if (isset($quantity['unit'])) {
                $this->setStrengthUnit($this->mapUnitToCode($quantity['unit']));
            }
        }

        return $this;
    }

    /**
     * Recherche par numéro CAS
     */
    public static function findByCasNumber(string $casNumber): ?self
    {
        $listing = self::getList();
        $listing->setCondition('casNumber = ?', [$casNumber]);
        $listing->setLimit(1);
        
        $results = $listing->load();
        return $results[0] ?? null;
    }

    /**
     * Recherche par INN
     */
    public static function findByInn(string $inn): ?self
    {
        $listing = self::getList();
        $listing->setCondition('inn = ?', [$inn]);
        $listing->setLimit(1);
        
        $results = $listing->load();
        return $results[0] ?? null;
    }

    /**
     * Recherche par nom de substance
     */
    public static function searchByName(string $name): array
    {
        $listing = self::getList();
        $listing->setCondition('substanceName LIKE ?', ['%' . $name . '%']);
        
        return $listing->load();
    }

    private function getSubstanceTypeDisplay(): string
    {
        $types = [
            'chemical' => 'Chimique',
            'protein' => 'Protéine',
            'nucleicAcid' => 'Acide nucléique',
            'polymer' => 'Polymère',
            'mixture' => 'Mélange'
        ];
        
        return $types[$this->getSubstanceType()] ?? '';
    }

    private function getStrengthUnitCode(): string
    {
        $unitCodes = [
            'mg' => 'mg',
            'g' => 'g',
            'mcg' => 'ug',
            'IU' => '[iU]',
            'mg/ml' => 'mg/mL',
            'percent' => '%'
        ];
        
        return $unitCodes[$this->getStrengthUnit()] ?? $this->getStrengthUnit();
    }

    private function mapUnitToCode(string $unit): string
    {
        $codeToUnit = [
            'mg' => 'mg',
            'g' => 'g',
            'ug' => 'mcg',
            '[iU]' => 'IU',
            'mg/mL' => 'mg/ml',
            '%' => 'percent'
        ];
        
        return $codeToUnit[$unit] ?? $unit;
    }
}