<?php
// src/Model/DataObject/MedicinalProduct.php
namespace App\Model\DataObject;

use App\Traits\FhirResourceTrait;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\MedicinalProduct as BaseMedicinalProduct;

class MedicinalProduct extends BaseMedicinalProduct
{
    use FhirResourceTrait;

    /**
     * Override setName pour gérer string et array
     */
    public function setName($name): self
    {
        // Si c'est un string (ancien format), le convertir
        if (is_string($name)) {
            // Créer un MedicinalProductName à la volée
            $productName = new DataObject\MedicinalProductName();
            $productName->setKey('name-temp-' . md5($name . time()));
            
            // Vérifier que le dossier existe
            $folder = DataObject::getByPath('/IDMP/MedicinalProductNames');
            if (!$folder) {
                $folder = new DataObject\Folder();
                $folder->setKey('MedicinalProductNames');
                $folder->setParent(DataObject::getByPath('/IDMP'));
                $folder->save();
            }
            
            $productName->setParent($folder);
            $productName->setProductName($name);
            $productName->setPublished(true);
            
            // Ne pas sauvegarder ici pour éviter les problèmes de performance
            // La sauvegarde se fera lors du save() du MedicinalProduct
            
            $name = [$productName];
        }
        
        // Appeler la méthode parent avec le format array
        return parent::setName($name);
    }

    /**
     * Helper pour récupérer le nom comme string
     */
    public function getNameAsString(): ?string
    {
        $names = $this->getName();
        if (is_array($names) && count($names) > 0) {
            return $names[0]->getProductName();
        }
        if (is_string($names)) {
            return $names;
        }
        return null;
    }

    /**
     * Override save pour sauvegarder les relations temporaires
     */
    public function save()
    {
        // Sauvegarder les MedicinalProductName temporaires
        $names = $this->getName();
        if (is_array($names)) {
            foreach ($names as $name) {
                if ($name && !$name->getId() && $name instanceof DataObject\MedicinalProductName) {
                    $name->save();
                }
            }
        }
        
        return parent::save();
    }
}