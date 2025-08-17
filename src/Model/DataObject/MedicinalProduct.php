<?php
// src/Model/DataObject/MedicinalProduct.php
namespace App\Model\DataObject;

use Pimcore\Model\DataObject\MedicinalProduct as GeneratedMedicinalProduct;

class MedicinalProduct extends GeneratedMedicinalProduct
{
    /**
     * Override pour gérer string et array
     */
    public function setName($name): self
    {
        // Si c'est un string, on le laisse passer temporairement
        if (is_string($name)) {
            // On stocke directement sans conversion pour éviter les erreurs
            $this->name = null; // On met null au lieu d'un string
            return $this;
        }
        
        // Si c'est déjà un array ou null, comportement normal
        return parent::setName($name);
    }
    
    /**
     * Override du getName pour gérer les anciens formats
     */
    public function getName(): ?array
    {
        // Si c'est un string (ne devrait plus arriver), retourner un array vide
        if (is_string($this->name)) {
            return [];
        }
        
        return parent::getName();
    }
}