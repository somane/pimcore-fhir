<?php
namespace App\Model\DataObject\Traits;

trait FhirTrait
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
            ...$this->toFhirResource()
        ];
    }
    
    abstract protected function getResourceType(): string;
    abstract protected function toFhirResource(): array;
}