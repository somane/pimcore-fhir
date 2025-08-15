<?php
// src/Model/DataObject/MedicinalProduct.php
namespace App\Model\DataObject;

use App\Traits\FhirResourceTrait;
use Pimcore\Model\DataObject\MedicinalProduct as BaseMedicinalProduct;

class MedicinalProduct extends BaseMedicinalProduct
{
    use FhirResourceTrait;

    /**
     * Convertit l'objet Pimcore en ressource FHIR MedicinalProduct
     */
    public function toFhirResource(): array
    {
        $resource = [
            'resourceType' => 'MedicinalProduct',
            'id' => $this->getMpid() ?: $this->getId(),
            'meta' => $this->getFhirMeta(),
            'identifier' => []
        ];

        // Identifiants
        if ($this->getMpid()) {
            $resource['identifier'][] = [
                'system' => 'urn:oid:2.16.840.1.113883.3.1937', // OID pour MPID
                'value' => $this->getMpid(),
                'use' => 'official'
            ];
        }

        // Nom commercial
        if ($this->getName()) {
            $resource['name'] = [[
                'productName' => $this->getName(),
                'nameType' => [
                    'coding' => [[
                        'system' => 'http://hl7.org/fhir/medicinal-product-name-type',
                        'code' => 'BAN',
                        'display' => 'Brand Name'
                    ]]
                ]
            ]];
        }

        // DCI (Dénomination Commune Internationale)
        if ($this->getNonproprietaryName()) {
            $resource['name'][] = [
                'productName' => $this->getNonproprietaryName(),
                'nameType' => [
                    'coding' => [[
                        'system' => 'http://hl7.org/fhir/medicinal-product-name-type',
                        'code' => 'INN',
                        'display' => 'International Nonproprietary Name'
                    ]]
                ]
            ];
        }

        // Type de produit
        if ($this->getProductType()) {
            $resource['type'] = [
                'coding' => [[
                    'system' => 'http://hl7.org/fhir/medicinal-product-type',
                    'code' => $this->getProductType(),
                    'display' => $this->getProductTypeDisplay()
                ]]
            ];
        }

        // Domaine d'utilisation
        if ($this->getDomain()) {
            $resource['domain'] = [
                'coding' => [[
                    'system' => 'http://hl7.org/fhir/medicinal-product-domain',
                    'code' => $this->getDomain(),
                    'display' => $this->getDomainDisplay()
                ]]
            ];
        }

        // Code ATC
        if ($this->getAtcCode()) {
            $resource['classification'] = [[
                'coding' => [[
                    'system' => 'http://www.whocc.no/atc',
                    'code' => $this->getAtcCode()
                ]]
            ]];
        }

        // Statut légal
        if ($this->getLegalStatusOfSupply()) {
            $resource['legalStatusOfSupply'] = [
                'coding' => [[
                    'system' => 'http://hl7.org/fhir/legal-status-of-supply',
                    'code' => $this->getLegalStatusOfSupply(),
                    'display' => $this->getLegalStatusDisplay()
                ]]
            ];
        }

        // Substances actives
        if ($this->getIngredient()) {
            $resource['ingredient'] = [];
            foreach ($this->getIngredient() as $substance) {
                $resource['ingredient'][] = [
                    'itemReference' => [
                        'reference' => 'Substance/' . $substance->getIdentifier()
                    ]
                ];
            }
        }

        // Forme pharmaceutique
        if ($this->getManufacturedItem()) {
            $resource['combinedPharmaceuticalDoseForm'] = [
                'coding' => [[
                    'system' => 'http://hl7.org/fhir/manufactured-dose-form',
                    'code' => $this->getManufacturedItem()->getDoseForm()
                ]]
            ];
        }

        // Titulaire de l'AMM
        if ($this->getMarketingAuthorizationHolder()) {
            $resource['marketingAuthorization'] = [[
                'holder' => [
                    'reference' => 'Organization/' . $this->getMarketingAuthorizationHolder()->getId()
                ]
            ]];
        }

        // Fabricants
        if ($this->getManufacturer()) {
            $resource['manufacturingBusinessOperation'] = [];
            foreach ($this->getManufacturer() as $manufacturer) {
                $resource['manufacturingBusinessOperation'][] = [
                    'manufacturer' => [[
                        'reference' => 'Organization/' . $manufacturer->getId()
                    ]]
                ];
            }
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
                if ($identifier['system'] === 'urn:oid:2.16.840.1.113883.3.1937') {
                    $this->setMpid($identifier['value']);
                }
            }
        }

        // Noms
        if (isset($resource['name'])) {
            foreach ($resource['name'] as $name) {
                $nameType = $name['nameType']['coding'][0]['code'] ?? null;
                if ($nameType === 'BAN') {
                    $this->setName($name['productName']);
                } elseif ($nameType === 'INN') {
                    $this->setNonproprietaryName($name['productName']);
                }
            }
        }

        // Type de produit
        if (isset($resource['type']['coding'][0]['code'])) {
            $this->setProductType($resource['type']['coding'][0]['code']);
        }

        // Domaine
        if (isset($resource['domain']['coding'][0]['code'])) {
            $this->setDomain($resource['domain']['coding'][0]['code']);
        }

        // Classification ATC
        if (isset($resource['classification'])) {
            foreach ($resource['classification'] as $classification) {
                if ($classification['coding'][0]['system'] === 'http://www.whocc.no/atc') {
                    $this->setAtcCode($classification['coding'][0]['code']);
                }
            }
        }

        // Statut légal
        if (isset($resource['legalStatusOfSupply']['coding'][0]['code'])) {
            $this->setLegalStatusOfSupply($resource['legalStatusOfSupply']['coding'][0]['code']);
        }

        return $this;
    }

    /**
     * Recherche par code ATC
     */
    public static function findByAtcCode(string $atcCode): ?self
    {
        $listing = self::getList();
        $listing->setCondition('atcCode = ?', [$atcCode]);
        $listing->setLimit(1);
        
        $results = $listing->load();
        return $results[0] ?? null;
    }

    /**
     * Recherche par DCI
     */
    public static function findByInn(string $inn): array
    {
        $listing = self::getList();
        $listing->setCondition('nonproprietaryName LIKE ?', ['%' . $inn . '%']);
        
        return $listing->load();
    }

    /**
     * Recherche par MPID
     */
    public static function findByMpid(string $mpid): ?self
    {
        $listing = self::getList();
        $listing->setCondition('mpid = ?', [$mpid]);
        $listing->setLimit(1);
        
        $results = $listing->load();
        return $results[0] ?? null;
    }

    private function getProductTypeDisplay(): string
    {
        $types = [
            'chemical' => 'Médicament chimique',
            'biological' => 'Médicament biologique',
            'vaccine' => 'Vaccin',
            'blood' => 'Produit sanguin',
            'radiopharmaceutical' => 'Radiopharmaceutique'
        ];
        
        return $types[$this->getProductType()] ?? '';
    }

    private function getDomainDisplay(): string
    {
        $domains = [
            'human' => 'Usage humain',
            'veterinary' => 'Usage vétérinaire',
            'both' => 'Usage humain et vétérinaire'
        ];
        
        return $domains[$this->getDomain()] ?? '';
    }

    private function getLegalStatusDisplay(): string
    {
        $statuses = [
            'prescription' => 'Sur ordonnance',
            'otc' => 'Sans ordonnance',
            'hospital' => 'Usage hospitalier',
            'narcotic' => 'Stupéfiant'
        ];
        
        return $statuses[$this->getLegalStatusOfSupply()] ?? '';
    }
}