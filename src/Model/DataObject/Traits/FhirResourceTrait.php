<?php
namespace App\Model\DataObject\Traits;

trait FhirResourceTrait
{
    public function toFhir(): array
    {
        return [
            'resourceType' => $this->getResourceType(),
            'id' => (string) $this->getId(),
            'meta' => [
                'lastUpdated' => $this->getModificationDate()->format('c'),
                'versionId' => (string) $this->getVersionCount()
            ],
            ...$this->toFhirArray()
        ];
    }
    
    abstract protected function getResourceType(): string;
    abstract protected function toFhirArray(): array;
}
