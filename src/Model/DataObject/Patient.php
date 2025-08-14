<?php
namespace App\Model\DataObject;

use Pimcore\Model\DataObject\Patient as BasePatient;
use App\Model\DataObject\Traits\FhirTrait;

class Patient extends BasePatient
{
    use FhirTrait;
    
    protected function getResourceType(): string
    {
        return 'Patient';
    }
    
    protected function toFhirResource(): array
    {
        return [
            'identifier' => [[
                'value' => $this->getIdentifier()
            ]],
            'name' => [[
                'family' => $this->getFamilyName(),
                'given' => [$this->getGivenName()]
            ]],
            'gender' => $this->getGender(),
            'birthDate' => $this->getBirthDate()?->format('Y-m-d')
        ];
    }
}