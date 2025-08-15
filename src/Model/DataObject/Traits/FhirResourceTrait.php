<?php
// src/Traits/FhirResourceTrait.php
namespace App\Traits;

trait FhirResourceTrait
{
    /**
     * Convertit l'objet en ressource FHIR
     * Cette méthode doit être implémentée dans chaque classe
     */
    abstract public function toFhirResource(): array;

    /**
     * Importe les données depuis une ressource FHIR
     * Cette méthode doit être implémentée dans chaque classe
     */
    abstract public function fromFhirResource(array $resource): self;

    /**
     * Génère les métadonnées FHIR
     */
    protected function getFhirMeta(): array
    {
        return [
            'versionId' => (string) $this->getVersionCount(),
            'lastUpdated' => $this->getModificationDate()->format('Y-m-d\TH:i:s\Z'),
            'source' => '#pimcore-fhir'
        ];
    }

    /**
     * Valide la ressource FHIR selon le profil
     */
    public function validateFhirResource(): array
    {
        $errors = [];
        $resource = $this->toFhirResource();

        // Vérifier la présence du resourceType
        if (!isset($resource['resourceType'])) {
            $errors[] = 'ResourceType is missing';
        }

        // Vérifier la présence de l'ID
        if (!isset($resource['id'])) {
            $errors[] = 'Resource ID is missing';
        }

        return $errors;
    }

    /**
     * Convertit en JSON FHIR
     */
    public function toFhirJson(): string
    {
        return json_encode($this->toFhirResource(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Convertit en XML FHIR
     */
    public function toFhirXml(): string
    {
        $resource = $this->toFhirResource();
        $resourceType = $resource['resourceType'];
        
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><' . $resourceType . ' xmlns="http://hl7.org/fhir"/>');
        
        $this->arrayToXml($resource, $xml);
        
        return $xml->asXML();
    }

    /**
     * Convertit un tableau en XML
     */
    private function arrayToXml(array $data, \SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            if ($key === 'resourceType') {
                continue;
            }

            if (is_array($value)) {
                if (is_numeric(key($value))) {
                    // Array numérique
                    foreach ($value as $item) {
                        $child = $xml->addChild($key);
                        if (is_array($item)) {
                            $this->arrayToXml($item, $child);
                        } else {
                            $child[0] = $item;
                        }
                    }
                } else {
                    // Array associatif
                    $child = $xml->addChild($key);
                    $this->arrayToXml($value, $child);
                }
            } else {
                $xml->addChild($key, htmlspecialchars($value));
            }
        }
    }

    /**
     * Génère un identifiant FHIR unique
     */
    protected function generateFhirId(): string
    {
        return $this->getClassName() . '-' . $this->getId();
    }

    /**
     * Ajoute une extension FHIR
     */
    protected function addExtension(array &$resource, string $url, $value): void
    {
        if (!isset($resource['extension'])) {
            $resource['extension'] = [];
        }

        $extension = ['url' => $url];

        if (is_string($value)) {
            $extension['valueString'] = $value;
        } elseif (is_bool($value)) {
            $extension['valueBoolean'] = $value;
        } elseif (is_int($value)) {
            $extension['valueInteger'] = $value;
        } elseif (is_float($value)) {
            $extension['valueDecimal'] = $value;
        } elseif (is_array($value)) {
            if (isset($value['coding'])) {
                $extension['valueCodeableConcept'] = $value;
            } else {
                $extension['valueObject'] = $value;
            }
        }

        $resource['extension'][] = $extension;
    }

    /**
     * Récupère une extension FHIR
     */
    protected function getExtension(array $resource, string $url)
    {
        if (!isset($resource['extension'])) {
            return null;
        }

        foreach ($resource['extension'] as $extension) {
            if ($extension['url'] === $url) {
                // Retourner la valeur selon son type
                foreach ($extension as $key => $value) {
                    if (strpos($key, 'value') === 0) {
                        return $value;
                    }
                }
            }
        }

        return null;
    }
}