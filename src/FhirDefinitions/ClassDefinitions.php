<?php

namespace App\FhirDefinitions;

use Exception;
use Pimcore\Model\DataObject\ClassDefinition;
use Symfony\Component\Console\Style\SymfonyStyle;

class ClassDefinitions
{
    public function __construct(
        private readonly FieldHelpers $fieldHelpers,
    ) {}

    /**
     * Generic upsert for any FHIR DataObject class.
     *
     * @param SymfonyStyle $io
     * @param string $name         Class name
     * @param string $description  Class description
     * @param array  $classFields  Array of DataDefinition instances
     * @throws Exception
     */
    private function upsertClass(
        SymfonyStyle $io,
        string $name,
        string $description,
        array  $classFields
    ): void {
        $class = ClassDefinition::getByName($name);
        if ($class) {
            $io->warning(sprintf('Class "%s" already exists. Updating for FHIR 6.0.0...', $name));
        } else {
            $class = new ClassDefinition();
            $class->setName($name);
            $io->writeln(sprintf('Creating new Class "%s" for FHIR 6.0.0...', $name));
        }

        $class->setDescription($description);
        $layout = $this->fieldHelpers->createDefaultPanelLayout($classFields);
        $class->setLayoutDefinitions($layout);
        $this->fieldHelpers->saveDefinition($class, $name, $io);
    }

    /**
     * FHIR Coding class (formerly FieldCollection)
     * @throws Exception
     */
    public function createCodingClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass(
            $io,
            'Coding',
            'FHIR 6.0.0 Coding',
            [
                $this->fieldHelpers->createInputField('system',      'System URI',    'Identifies the terminology system (e.g., SNOMED CT, LOINC).', false, 400),
                $this->fieldHelpers->createInputField('version',     'System Version','The version of the terminology system.',              false, 200),
                $this->fieldHelpers->createInputField('code',        'Code',          'The symbol in the system that identifies the concept.', true, 200),
                $this->fieldHelpers->createInputField('display',     'Display Value','A human‑readable representation of the code.',        false, 400),
                $this->fieldHelpers->createCheckboxField('userSelected','User Selected','Indicates this coding was chosen by a user.'),
            ]
        );
    }

    /**
     * FHIR CodeableConcept class, with multi-relation to Coding
     * @throws Exception
     */
    public function createCodeableConceptClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass(
            $io,
            'CodeableConcept',
            'FHIR 6.0.0 CodeableConcept',
            [
                $this->fieldHelpers->createInputField('text',    'Text',    'Human‑readable text for the concept.', false, 400),
                $this->fieldHelpers->createObjectRelationField(
                    'codings',
                    'Codings',
                    ['Coding'],
                    'One or more codings representing the concept.',
                    false,
                    true
                ),
            ]
        );
    }

    /**
     * FHIR Period class
     * @throws Exception
     */
    public function createPeriodClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass(
            $io,
            'Period',
            'FHIR 6.0.0 Period',
            [
                $this->fieldHelpers->createDatetimeField('start','Start Date/Time','The start of the period.'),
                $this->fieldHelpers->createDatetimeField('end',  'End Date/Time',  'The end of the period.'),
            ]
        );
    }

    /**
     * FHIR Quantity class (formerly FieldCollection)
     * @throws Exception
     */
    public function createQuantityClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'Quantity',
            'FHIR 6.0.0 Quantity',
            [
                $this->fieldHelpers->createNumericField('quantityValue','Value','The numerical value of the quantity.'),
                $this->fieldHelpers->createInputField('comparator','Comparator','How the value should be interpreted (e.g., <, <=).', false, 100),
                $this->fieldHelpers->createInputField('unit','Unit','The unit of measure (e.g., mg).', false, 150),
                $this->fieldHelpers->createInputField('system','System URI','System defining the unit (e.g., UCUM).', false, 300),
                $this->fieldHelpers->createInputField('code','Code','A code for the unit (e.g., mg).', false, 150),
            ]
        );
    }

    /**
     * FHIR Identifier class (formerly FieldCollection)
     * @throws Exception
     */
    public function createIdentifierClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'Identifier',
            'FHIR 6.0.0 Identifier',
            [
                $this->fieldHelpers->createInputField('use','Use','The purpose (e.g., usual, official).', false, 200),
                $this->fieldHelpers->createObjectRelationField('identifierType','Type',['CodeableConcept'],'Coded type for the identifier.', false, false),
                $this->fieldHelpers->createInputField('system','System URI','Namespace for the identifier value.', false, 400),
                $this->fieldHelpers->createInputField('identifierValue','Value','The identifier value.', false, 300),
                $this->fieldHelpers->createObjectRelationField('period','Period',['Period'],'Validity period of the identifier.', false, false),
            ]
        );
    }

    /**
     * FHIR Ratio class (formerly FieldCollection)
     * @throws Exception
     */
    public function createRatioClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'Ratio',
            'FHIR 6.0.0 Ratio',
            [
                $this->fieldHelpers->createObjectRelationField('numerator','Numerator',['Quantity'],'The value of the numerator.', false, false),
                $this->fieldHelpers->createObjectRelationField('denominator','Denominator',['Quantity'],'The value of the denominator.', false, false),
            ]
        );
    }

    /**
     * FHIR ContactPoint class (formerly FieldCollection)
     * @throws Exception
     */
    public function createContactPointClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'ContactPoint',
            'FHIR 6.0.0 Contact Point',
            [
                $this->fieldHelpers->createInputField('system','System','Telecom system (phone, email).', false, 200),
                $this->fieldHelpers->createInputField('contactPointValue','Value','The actual contact detail.', false, 300),
                $this->fieldHelpers->createInputField('use','Use','Purpose (home, work).', false, 200),
                $this->fieldHelpers->createNumericField('rank','Rank','Preferred order of use.'),
                $this->fieldHelpers->createObjectRelationField('period','Period',['Period'],'Time period of use.', false, false),
            ]
        );
    }

    /**
     * FHIR MarketingStatus class (formerly FieldCollection)
     * @throws Exception
     */
    public function createMarketingStatusClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'MarketingStatus',
            'FHIR 6.0.0 Marketing Status',
            [
                // Note: 'applies' was a required field in the old version.
                // Assuming the new createObjectRelationField helper handles this internally, or it's no longer required.
                $this->fieldHelpers->createObjectRelationField('applies','Applies To',['CodeableConcept'],'Authorization holder.', false, false),
                $this->fieldHelpers->createObjectRelationField('jurisdiction','Jurisdiction',['CodeableConcept'],'Territorial jurisdiction.', false, false),
                $this->fieldHelpers->createObjectRelationField('legalStatusOfSupply','Legal Status of Supply',['CodeableConcept'],'Regulated supply status.', false, false),
                $this->fieldHelpers->createObjectRelationField('dateRange','Date Range',['Period'],'Effective and/or ceased dates.', false, false),
                $this->fieldHelpers->createDatetimeField('restoreDate','Restore Date','When status became applicable.'),
            ]
        );
    }

    /**
     * FHIR ProductShelfLife class (formerly FieldCollection)
     * @throws Exception
     */
    public function createProductShelfLifeClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'ProductShelfLife',
            'FHIR 6.0.0 Product Shelf Life',
            [
                $this->fieldHelpers->createObjectRelationField('shelfLifeType','Type',['CodeableConcept'],'e.g., first opening, after reconstitution.', false, false),
                $this->fieldHelpers->createObjectRelationField('period','Period',['Quantity'],'Expected stability duration.', false, false),
                $this->fieldHelpers->createObjectRelationField('specialPrecautionsForStorage','Special Precautions',['CodeableConcept'],'Storage instructions.', false, true),
            ]
        );
    }

    /**
     * FHIR ContactDetail class (formerly FieldCollection)
     * @throws Exception
     */
    public function createContactDetailClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'ContactDetail',
            'FHIR 6.0.0 Contact Detail',
            [
                $this->fieldHelpers->createObjectRelationField('purpose','Purpose',['CodeableConcept'],'Purpose of contact info.', false, false),
                $this->fieldHelpers->createInputField('name','Name','Individual to contact.', false, 300),
                $this->fieldHelpers->createObjectRelationField('telecom','Telecom',['ContactPoint'],'Communication contacts.', false, true),
            ]
        );
    }

    /**
     * FHIR Attachment class (formerly FieldCollection)
     * @throws Exception
     */
    public function createAttachmentClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'Attachment',
            'FHIR 6.0.0 Attachment',
            [
                $this->fieldHelpers->createInputField('contentType','Content Type','Mime type (e.g., image/png).', false, 200),
                $this->fieldHelpers->createInputField('attachmentLanguage','Language','BCP-47 code.', false, 100),
                $this->fieldHelpers->createTextareaField('attachmentData','Data (Base64)','Base64-encoded content.'),
                $this->fieldHelpers->createInputField('url','URL','Link to the attachment.', false, 400),
                $this->fieldHelpers->createNumericField('size','Size','Number of bytes.'),
                $this->fieldHelpers->createTextareaField('hash','Hash','SHA-1 of content.'),
                $this->fieldHelpers->createInputField('title','Title','Label for the attachment.', false, 300),
                $this->fieldHelpers->createDatetimeField('creation','Creation Date/Time','When it was created.'),
            ]
        );
    }

    /**
     * FHIR Reference class (formerly FieldCollection)
     * @throws Exception
     */
    public function createReferenceClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'Reference',
            'FHIR 6.0.0 Reference',
            [
                $this->fieldHelpers->createInputField('reference','Reference','Reference to another resource.', false, 400),
                $this->fieldHelpers->createInputField('display','Display','Text representation of the reference.', false, 300),
            ]
        );
    }

    /**
     * ManufacturingBusinessOperation class (formerly FieldCollection)
     * @throws Exception
     */
    public function createManufacturingBusinessOperationClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'ManufacturingBusinessOperation',
            'FHIR 6.0.0 Manufacturing Business Operation',
            [
                $this->fieldHelpers->createObjectRelationField('operationType','Operation Type',['CodeableConcept'],'Type of manufacturing operation.', false, false),
                $this->fieldHelpers->createObjectRelationField('authorisationReferenceNumber','Authorisation Reference Number',['Identifier'],'Authorization number.', false, false),
                $this->fieldHelpers->createObjectRelationField('effectiveDate','Effective Date',['Period'],'Effective period.', false, false),
                $this->fieldHelpers->createObjectRelationField('manufacturer','Manufacturer',['Reference'],'Manufacturer reference.', false, false),
                $this->fieldHelpers->createObjectRelationField('regulator','Regulator',['Reference'],'Regulator reference.', false, false),
            ]
        );
    }

    /**
     * MedicinalProductName class (formerly FieldCollection)
     * @throws Exception
     */
    public function createMedicinalProductNameClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'MedicinalProductName',
            'FHIR 6.0.0 Medicinal Product Name',
            [
                $this->fieldHelpers->createInputField('productName','Product Name','The full product name.', true, 400),
                $this->fieldHelpers->createObjectRelationField('nameType','Name Type',['CodeableConcept'],'e.g., invented, scientific.', false, false),
                $this->fieldHelpers->createObjectRelationField('part','Part',['CodeableConcept'],'Name parts (strength, form).', false, true),
                $this->fieldHelpers->createObjectRelationField('usage','Usage',['CodeableConcept'],'Usage context (official, synonym).', false, true),
            ]
        );
    }

    /**
     * MedicinalProductProperty class (formerly FieldCollection)
     * @throws Exception
     */
    public function createMedicinalProductPropertyClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'MedicinalProductProperty',
            'FHIR 6.0.0 Medicinal Product Property',
            [
                $this->fieldHelpers->createObjectRelationField('propertyType','Property Type',['CodeableConcept'],'Property type.', true, false),
                $this->fieldHelpers->createInputField('propertyValue','Property Value','Property value (string or quantity).', false, 400),
            ]
        );
    }

    /**
     * MedicinalProductCrossReference class (formerly FieldCollection)
     * @throws Exception
     */
    public function createMedicinalProductCrossReferenceClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'MedicinalProductCrossReference',
            'FHIR 6.0.0 Cross Reference',
            [
                $this->fieldHelpers->createObjectRelationField('product','Product Reference',['Reference'],'Reference to another product.', true, false),
                $this->fieldHelpers->createObjectRelationField('referenceType','Reference Type',['CodeableConcept'],'Type of reference.', false, false),
            ]
        );
    }

    /**
     * RegulatedAuthorizationCase class (formerly FieldCollection)
     * @throws Exception
     */
    public function createRegulatedAuthorizationCaseClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'RegulatedAuthorizationCase',
            'FHIR 6.0.0 Regulated Authorization Case',
            [
                $this->fieldHelpers->createObjectRelationField('identifier','Identifier',['Identifier'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('caseType','Type',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('status','Status',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('datePeriod','Date Period',['Period'], '', false, false),
                $this->fieldHelpers->createDatetimeField('dateDateTime','Date/Time'),
            ]
        );
    }

    /**
     * PackagedProductLegalStatusOfSupply class (formerly FieldCollection)
     * @throws Exception
     */
    public function createPackagedProductLegalStatusOfSupplyClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'PackagedProductLegalStatusOfSupply',
            'FHIR 6.0.0 Legal Status of Supply',
            [
                $this->fieldHelpers->createObjectRelationField('code','Code',['CodeableConcept'],'Status of supply.', false, false),
                $this->fieldHelpers->createObjectRelationField('jurisdiction','Jurisdiction',['CodeableConcept'],'Place where status applies.', false, false),
            ]
        );
    }

    /**
     * ManufacturedItemProperty class (formerly FieldCollection)
     * @throws Exception
     */
    public function createManufacturedItemPropertyClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'ManufacturedItemProperty',
            'FHIR 6.0.0 Manufactured Item Property',
            [
                $this->fieldHelpers->createObjectRelationField('propertyType','Type',['CodeableConcept'],'Characteristic type.', false, false),
                $this->fieldHelpers->createObjectRelationField('valueCodeableConcept','Value (CodeableConcept)',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('valueQuantity','Value (Quantity)',['Quantity'], '', false, false),
                $this->fieldHelpers->createDatetimeField('valueDate','Value (Date)'),
                $this->fieldHelpers->createCheckboxField('valueBoolean','Value (Boolean)'),
                $this->fieldHelpers->createTextareaField('valueMarkdown','Value (Markdown)'),
                $this->fieldHelpers->createObjectRelationField('valueAttachment','Value (Attachment)',['Attachment'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('valueReference','Value (Reference)',['Reference'],'Reference to a resource.', false, false),
            ]
        );
    }

    /**
     * ManufacturedItemConstituent class (formerly FieldCollection)
     * @throws Exception
     */
    public function createManufacturedItemConstituentClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'ManufacturedItemConstituent',
            'FHIR 6.0.0 Constituent',
            [
                $this->fieldHelpers->createObjectRelationField('amount','Amount',['Quantity'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('location','Location',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('function','Function',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('hasIngredient','Has Ingredient',['Reference'],'Reference to Ingredient.', false, true),
            ]
        );
    }

    /**
     * ManufacturedItemComponent class (formerly FieldCollection)
     * @throws Exception
     */
    public function createManufacturedItemComponentClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'ManufacturedItemComponent',
            'FHIR 6.0.0 Component',
            [
                $this->fieldHelpers->createObjectRelationField('componentType','Type',['CodeableConcept'],'Defining type of the component.', false, false),
                $this->fieldHelpers->createObjectRelationField('function','Function(s)',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('amount','Amount(s)',['Quantity'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('constituent','Constituent(s)',['ManufacturedItemConstituent'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('componentProperty','Property(s)',['ManufacturedItemProperty'], '', false, true),
                // This relation points to the 'Child' class to avoid direct recursion
                $this->fieldHelpers->createObjectRelationField('components','Component(s)',['ManufacturedItemComponentChild'],'Nested components.', false, true),
            ]
        );
    }

    /**
     * ManufacturedItemComponentChild class (formerly FieldCollection)
     * A simplified version of Component for safe recursion.
     * @throws Exception
     */
    public function createManufacturedItemComponentChildClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'ManufacturedItemComponentChild',
            'FHIR 6.0.0 Child Component',
            [
                $this->fieldHelpers->createObjectRelationField('componentType','Type',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('function','Function(s)',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('amount','Amount(s)',['Quantity'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('constituent','Constituent(s)',['ManufacturedItemConstituent'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('componentProperty','Property(s)',['ManufacturedItemProperty'], '', false, true),
            ]
        );
    }

    /**
     * AdministrableProductProperty class (formerly FieldCollection)
     * @throws Exception
     */
    public function createAdministrableProductPropertyClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'AdministrableProductProperty',
            'FHIR 6.0.0 Administrable Product Property',
            [
                $this->fieldHelpers->createObjectRelationField('propertyType','Type',['CodeableConcept'],'Characteristic type.', false, false),
                $this->fieldHelpers->createObjectRelationField('valueCodeableConcept','Value (CodeableConcept)',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('valueQuantity','Value (Quantity)',['Quantity'], '', false, false),
                $this->fieldHelpers->createDatetimeField('valueDate','Value (Date)'),
                $this->fieldHelpers->createCheckboxField('valueBoolean','Value (Boolean)'),
                $this->fieldHelpers->createTextareaField('valueMarkdown','Value (Markdown)'),
                $this->fieldHelpers->createObjectRelationField('valueAttachment','Value (Attachment)',['Attachment'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('valueReference','Value (Reference)',['Reference'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('status','Status',['CodeableConcept'],'Status of the characteristic.', false, false),
            ]
        );
    }

    /**
     * AdministrableProductRouteOfAdministration class (formerly FieldCollection)
     * @throws Exception
     */
    public function createAdministrableProductRouteOfAdministrationClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'APDRouteOfAdmin',
            'FHIR 6.0.0 Route of Administration',
            [
                $this->fieldHelpers->createObjectRelationField('code','Route Code',['CodeableConcept'],'Coded route.', false, false),
                $this->fieldHelpers->createObjectRelationField('firstDose','First Dose',['Quantity'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('maxSingleDose','Max Single Dose',['Quantity'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('maxDosePerDay','Max Dose Per Day',['Quantity'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('maxDosePerTreatmentPeriod','Max Dose Per Treatment Period',['Ratio'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('maxTreatmentPeriod','Max Treatment Period',['Quantity'],'Duration.', false, false),
                $this->fieldHelpers->createObjectRelationField('targetSpecies','Target Species',['AdministrableProductRouteOfAdministrationTargetSpecies'],'Species applicable.', false, true),
            ]
        );
    }

    /**
     * AdministrableProductRouteOfAdministrationTargetSpecies class (formerly FieldCollection)
     * @throws Exception
     */
    public function createAdministrableProductRouteOfAdministrationTargetSpeciesClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'AdministrableProductRouteOfAdministrationTargetSpecies',
            'FHIR 6.0.0 Target Species',
            [
                $this->fieldHelpers->createObjectRelationField('code','Species Code',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('withdrawalPeriod','Withdrawal Period(s)',['AdministrableProductRouteOfAdministrationTargetSpeciesWithdrawalPeriod'],'Withdrawal period for animal products.', false, true),
            ]
        );
    }

    /**
     * AdministrableProductRouteOfAdministrationTargetSpeciesWithdrawalPeriod class (formerly FieldCollection)
     * @throws Exception
     */
    public function createAdministrableProductRouteOfAdministrationTargetSpeciesWithdrawalPeriodClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'AdministrableProductRouteOfAdministrationTargetSpeciesWithdrawalPeriod',
            'FHIR 6.0.0 Withdrawal Period',
            [
                $this->fieldHelpers->createObjectRelationField('tissue','Tissue',['CodeableConcept'],'Type of tissue.', false, false),
                $this->fieldHelpers->createObjectRelationField('withdrawalValue','Value',['Quantity'],'Withdrawal time period.', false, false),
                $this->fieldHelpers->createInputField('supportingInformation','Supporting Information','Extra info.', false, 200),
            ]
        );
    }

    /**
     * IngredientManufacturer class (formerly FieldCollection)
     * @throws Exception
     */
    public function createIngredientManufacturerClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'IngredientManufacturer',
            'FHIR 6.0.0 Ingredient Manufacturer',
            [
                $this->fieldHelpers->createInputField('role','Role','allowed | possible | actual', false, 80),
                $this->fieldHelpers->createObjectRelationField('manufacturer','Manufacturer',['Reference'],'Organization manufacturing this ingredient.', false, false),
            ]
        );
    }

    /**
     * IngredientSubstance class (formerly FieldCollection)
     * @throws Exception
     */
    public function createIngredientSubstanceClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'IngredientSubstance',
            'FHIR 6.0.0 Ingredient Substance',
            [
                $this->fieldHelpers->createObjectRelationField('code','Substance Code',['CodeableConcept'],'The code or resource representing the ingredient substance.', false, true),
                $this->fieldHelpers->createObjectRelationField('strength','Strength(s)',['IngredientSubstanceStrength'],'Strength(s) of the substance.', false, true),
            ]
        );
    }

    /**
     * IngredientSubstanceStrength class (formerly FieldCollection)
     * @throws Exception
     */
    public function createIngredientSubstanceStrengthClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'IngredientSubstanceStrength',
            'FHIR 6.0.0 Ingredient Substance Strength',
            [
                $this->fieldHelpers->createObjectRelationField('presentationRatio','Presentation Ratio',['Ratio'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('presentationRatioRange','Presentation Ratio Range',['RatioRange'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('presentationCodeableConcept','Presentation (CodeableConcept)',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('presentationQuantity','Presentation (Quantity)',['Quantity'], '', false, false),
                $this->fieldHelpers->createInputField('textPresentation','Text Presentation','Textual representation.', false, 200),

                $this->fieldHelpers->createObjectRelationField('concentrationRatio','Concentration Ratio',['Ratio'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('concentrationRatioRange','Concentration Ratio Range',['RatioRange'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('concentrationCodeableConcept','Concentration (CodeableConcept)',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('concentrationQuantity','Concentration (Quantity)',['Quantity'], '', false, false),
                $this->fieldHelpers->createInputField('textConcentration','Text Concentration','Textual representation.', false, 200),

                $this->fieldHelpers->createObjectRelationField('basis','Basis',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createInputField('measurementPoint','Measurement Point','Where measured.', false, 200),
                $this->fieldHelpers->createObjectRelationField('country','Country',['CodeableConcept'],'Where strength applies.', false, true),
                $this->fieldHelpers->createObjectRelationField('referenceStrength','Reference Strength(s)',['IngredientSubstanceStrengthReferenceStrength'], '', false, true),
            ]
        );
    }

    /**
     * IngredientSubstanceStrengthReferenceStrength class (formerly FieldCollection)
     * @throws Exception
     */
    public function createIngredientSubstanceStrengthReferenceStrengthClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'IngredientSubstanceStrengthReferenceStrength',
            'FHIR 6.0.0 Reference Strength',
            [
                $this->fieldHelpers->createObjectRelationField('substance','Substance',['CodeableConcept'],'Reference substance.', false, false),
                $this->fieldHelpers->createObjectRelationField('strengthRatio','Strength Ratio',['Ratio'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('strengthRatioRange','Strength Ratio Range',['RatioRange'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('strengthQuantity','Strength (Quantity)',['Quantity'], '', false, false),
                $this->fieldHelpers->createInputField('measurementPoint','Measurement Point','', false, 200),
                $this->fieldHelpers->createObjectRelationField('country','Country',['CodeableConcept'], '', false, true),
            ]
        );
    }

    /**
     * ClinicalUseContraindication class (formerly FieldCollection)
     * @throws Exception
     */
    public function createClinicalUseContraindicationClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'ClinicalUseContraindication',
            'FHIR 6.0.0 Contraindication',
            [
                $this->fieldHelpers->createObjectRelationField('diseaseSymptomProcedure','Disease/Symptom/Procedure',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('diseaseStatus','Disease Status',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('comorbidity','Comorbidity',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('indication','Indication',['Reference'], '', false, true),
                $this->fieldHelpers->createTextareaField('applicability','Applicability','Expression as string.'),
                $this->fieldHelpers->createObjectRelationField('otherTherapy','Other Therapy',['ClinicalUseOtherTherapy'], '', false, true),
            ]
        );
    }

    /**
     * ClinicalUseOtherTherapy class (formerly FieldCollection)
     * @throws Exception
     */
    public function createClinicalUseOtherTherapyClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'ClinicalUseOtherTherapy',
            'FHIR 6.0.0 Other Therapy',
            [
                $this->fieldHelpers->createObjectRelationField('relationshipType','Relationship Type',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('treatment','Treatment',['CodeableConcept'],'Reference to the specific medication.', false, false),
            ]
        );
    }

    /**
     * ClinicalUseIndication class (formerly FieldCollection)
     * @throws Exception
     */
    public function createClinicalUseIndicationClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'ClinicalUseIndication',
            'FHIR 6.0.0 Indication',
            [
                $this->fieldHelpers->createObjectRelationField('diseaseSymptomProcedure','Disease/Symptom/Procedure',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('diseaseStatus','Disease Status',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('comorbidity','Comorbidity',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('intendedEffect','Intended Effect',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('durationRange','Duration Range',['Range'], '', false, false),
                $this->fieldHelpers->createInputField('durationString','Duration (String)','', false, 200),
                $this->fieldHelpers->createObjectRelationField('undesirableEffect','Undesirable Effect',['Reference'], '', false, true),
                $this->fieldHelpers->createTextareaField('applicability','Applicability','Expression as string.'),
                $this->fieldHelpers->createObjectRelationField('otherTherapy','Other Therapy',['ClinicalUseOtherTherapy'], '', false, true),
            ]
        );
    }

    /**
     * ClinicalUseInteraction class (formerly FieldCollection)
     * @throws Exception
     */
    public function createClinicalUseInteractionClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'ClinicalUseInteraction',
            'FHIR 6.0.0 Interaction',
            [
                $this->fieldHelpers->createObjectRelationField('interactant','Interactant',['ClinicalUseInteractionInteractant'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('interactionType','Type',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('effect','Effect',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('incidence','Incidence',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('management','Management',['CodeableConcept'], '', false, true),
            ]
        );
    }

    /**
     * ClinicalUseInteractionInteractant class (formerly FieldCollection)
     * @throws Exception
     */
    public function createClinicalUseInteractionInteractantClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'ClinicalUseInteractionInteractant',
            'FHIR 6.0.0 Interactant',
            [
                $this->fieldHelpers->createObjectRelationField('itemReference','Item (Reference)',['Reference'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('itemCodeableConcept','Item (CodeableConcept)',['CodeableConcept'], '', false, false),
            ]
        );
    }

    /**
     * ClinicalUseUndesirableEffect class (formerly FieldCollection)
     * @throws Exception
     */
    public function createClinicalUseUndesirableEffectClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'ClinicalUseUndesirableEffect',
            'FHIR 6.0.0 Undesirable Effect',
            [
                $this->fieldHelpers->createObjectRelationField('symptomConditionEffect','Symptom/Condition/Effect',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('classification','Classification',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('frequencyOfOccurrence','Frequency Of Occurrence',['CodeableConcept'], '', false, false),
            ]
        );
    }

    /**
     * ClinicalUseWarning class (formerly FieldCollection)
     * @throws Exception
     */
    public function createClinicalUseWarningClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'ClinicalUseWarning',
            'FHIR 6.0.0 Warning',
            [
                $this->fieldHelpers->createTextareaField('description','Description','Warning text.'),
                $this->fieldHelpers->createObjectRelationField('code','Code',['CodeableConcept'],'Coded or unformatted warning.', false, false),
            ]
        );
    }

    /**
     * SubstanceMoiety class (formerly FieldCollection)
     * @throws Exception
     */
    public function createSubstanceMoietyClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'SubstanceMoiety',
            'FHIR 6.0.0 Moiety',
            [
                $this->fieldHelpers->createObjectRelationField('role','Role',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('identifier','Identifier',['Identifier'], '', false, false),
                $this->fieldHelpers->createInputField('name','Name','', false, 200),
                $this->fieldHelpers->createObjectRelationField('stereochemistry','Stereochemistry',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('opticalActivity','Optical Activity',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createInputField('molecularFormula','Molecular Formula','', false, 200),
                $this->fieldHelpers->createObjectRelationField('amountQuantity','Amount (Quantity)',['Quantity'], '', false, false),
                $this->fieldHelpers->createInputField('amountString','Amount (String)','', false, 200),
                $this->fieldHelpers->createObjectRelationField('measurementType','Measurement Type',['CodeableConcept'], '', false, false),
            ]
        );
    }

    /**
     * SubstanceProperty class (formerly FieldCollection)
     * @throws Exception
     */
    public function createSubstancePropertyClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'SubstanceProperty',
            'FHIR 6.0.0 Substance Property',
            [
                $this->fieldHelpers->createObjectRelationField('propertyType','Type',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('valueCodeableConcept','Value (CodeableConcept)',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('valueQuantity','Value (Quantity)',['Quantity'], '', false, false),
                $this->fieldHelpers->createInputField('valueDate','Value (Date)','', false, 200),
                $this->fieldHelpers->createCheckboxField('valueBoolean','Value (Boolean)'),
                $this->fieldHelpers->createObjectRelationField('valueAttachment','Value (Attachment)',['Attachment'], '', false, false),
            ]
        );
    }

    /**
     * SubstanceStructureRepresentation class (formerly FieldCollection)
     * @throws Exception
     */
    public function createSubstanceStructureRepresentationClass(SymfonyStyle $io): void
    {
        $this->upsertClass($io,
            'SubstanceStructureRepresentation',
            'FHIR 6.0.0 Structure Representation',
            [
                $this->fieldHelpers->createObjectRelationField('representationType','Type',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createInputField('representation','Representation','', false, 400),
                $this->fieldHelpers->createObjectRelationField('format','Format',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('document','Document',['Reference'], '', false, false),
            ]
        );
    }

    /**
     * SubstanceStructure class (formerly FieldCollection)
     * @throws Exception
     */
    public function createSubstanceStructureClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'SubstanceStructure',
            'FHIR 6.0.0 Substance Structure',
            [
                $this->fieldHelpers->createObjectRelationField('stereochemistry','Stereochemistry',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('opticalActivity','Optical Activity',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createInputField('molecularFormula','Molecular Formula','', false, 200),
                $this->fieldHelpers->createInputField('molecularFormulaByMoiety','Molecular Formula By Moiety','', false, 200),
                $this->fieldHelpers->createObjectRelationField('molecularWeight','Molecular Weight',['SubstanceMolecularWeight'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('technique','Technique',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('sourceDocument','Source Document',['Reference'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('representation','Representation',['SubstanceStructureRepresentation'], '', false, true),
            ]
        );
    }

    /**
     * SubstanceCode class (formerly FieldCollection)
     * @throws Exception
     */
    public function createSubstanceCodeClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'SubstanceCode',
            'FHIR 6.0.0 Substance Code',
            [
                $this->fieldHelpers->createObjectRelationField('code','Code',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('status','Status',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createInputField('statusDate','Status Date','', false, 100),
                $this->fieldHelpers->createObjectRelationField('note','Note',['Annotation'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('source','Source',['Reference'], '', false, true),
            ]
        );
    }

    /**
     * SubstanceNameOfficial class (formerly FieldCollection)
     * @throws Exception
     */
    public function createSubstanceNameOfficialClass(SymfonyStyle $io): void
    {
        $this->upsertClass($io,
            'SubstanceNameOfficial',
            'FHIR 6.0.0 Name Official Details',
            [
                $this->fieldHelpers->createObjectRelationField('authority','Authority',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('status','Status',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createInputField('date','Date','', false, 100),
            ]
        );
    }

    /**
     * SubstanceNameChild class to avoid recursion.
     * @throws Exception
     */
    public function createSubstanceNameChildClass(SymfonyStyle $io): void
    {
        $this->upsertClass($io,
            'SubstanceNameChild',
            'FHIR 6.0.0 Substance Name (Child for recursion)',
            [
                $this->fieldHelpers->createInputField('name','Name','', false, 400),
                $this->fieldHelpers->createObjectRelationField('nameType','Type',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('status','Status',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createCheckboxField('preferred','Preferred'),
                $this->fieldHelpers->createObjectRelationField('nameLanguage','Language',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('domain','Domain',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('jurisdiction','Jurisdiction',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('official','Official',['SubstanceNameOfficial'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('source','Source',['Reference'], '', false, true),
            ]
        );
    }

    /**
     * SubstanceName class (formerly FieldCollection)
     * @throws Exception
     */
    public function createSubstanceNameClass(SymfonyStyle $io): void
    {
        $this->upsertClass($io,
            'SubstanceName',
            'FHIR 6.0.0 Substance Name',
            [
                $this->fieldHelpers->createInputField('name','Name','', false, 400),
                $this->fieldHelpers->createObjectRelationField('nameType','Type',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('status','Status',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createCheckboxField('preferred','Preferred'),
                $this->fieldHelpers->createObjectRelationField('nameLanguage','Language',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('domain','Domain',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('jurisdiction','Jurisdiction',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('synonym','Synonym',['SubstanceNameChild'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('translation','Translation',['SubstanceNameChild'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('official','Official',['SubstanceNameOfficial'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('source','Source',['Reference'], '', false, true),
            ]
        );
    }

    /**
     * SubstanceRelationship class (formerly FieldCollection)
     * @throws Exception
     */
    public function createSubstanceRelationshipClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'SubstanceRelationship',
            'FHIR 6.0.0 Substance Relationship',
            [
                $this->fieldHelpers->createObjectRelationField('substanceReference','Substance (Reference)',['Reference'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('substanceCodeableConcept','Substance (CodeableConcept)',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('relationshipType','Type',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createCheckboxField('isDefining','Is Defining'),
                $this->fieldHelpers->createObjectRelationField('amountQuantity','Amount (Quantity)',['Quantity'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('amountRatio','Amount (Ratio)',['Ratio'], '', false, false),
                $this->fieldHelpers->createInputField('amountString','Amount (String)','', false, 200),
                $this->fieldHelpers->createObjectRelationField('ratioHighLimitAmount','Ratio High Limit Amount',['Ratio'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('comparator','Comparator',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('source','Source',['Reference'], '', false, true),
            ]
        );
    }

    /**
     * FHIR Annotation class (formerly FieldCollection)
     * @throws Exception
     */
    public function createAnnotationClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'Annotation',
            'FHIR 6.0.0 Annotation',
            [
                $this->fieldHelpers->createInputField('text','Text','The annotation text.', true, 1000),
                $this->fieldHelpers->createInputField('authorString','Author (String)','Plain author text.', false, 255),
                $this->fieldHelpers->createObjectRelationField('authorReference','Author (Reference)',['Reference'], '', false, false),
                $this->fieldHelpers->createDatetimeField('time','Time','When the annotation was made.'),
            ]
        );
    }

    /**
     * FHIR Range class (formerly FieldCollection)
     * @throws Exception
     */
    public function createRangeClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'Range',
            'FHIR 6.0.0 Range',
            [
                $this->fieldHelpers->createObjectRelationField('low','Low',['Quantity'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('high','High',['Quantity'], '', false, false),
            ]
        );
    }

    /**
     * FHIR RatioRange class (formerly FieldCollection)
     * @throws Exception
     */
    public function createRatioRangeClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'RatioRange',
            'FHIR 6.0.0 RatioRange',
            [
                $this->fieldHelpers->createObjectRelationField('lowNumerator','Low Numerator',['Quantity'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('highNumerator','High Numerator',['Quantity'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('lowDenominator','Low Denominator',['Quantity'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('highDenominator','High Denominator',['Quantity'], '', false, false),
            ]
        );
    }

    /**
     * PackagedProductPackaging class (formerly FieldCollection)
     * @throws Exception
     */
    public function createPackagedProductPackagingClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'PackagedProductPackaging',
            'FHIR 6.0.0 Packaging',
            [
                $this->fieldHelpers->createObjectRelationField('identifier','Identifier(s)',['Identifier'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('packagingType','Type',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('quantity','Quantity',['Quantity'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('material','Material(s)',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('alternateMaterial','Alternate Material(s)',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('shelfLifeStorage','Shelf Life / Storage',['ProductShelfLife'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('manufacturer','Manufacturer(s)',['Reference'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('characteristic','Characteristic(s)',['PackagedProductCharacteristic'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('containedItem','Contained Item(s)',['PackagedProductPackagingContainedItem'], '', false, true),
                // This relation points to the 'Child' class to avoid direct recursion
                $this->fieldHelpers->createObjectRelationField('packaging','Inner Packaging',['PackagedProductPackagingChild'],'Nested packaging levels.', false, true),
                $this->fieldHelpers->createTextareaField('description','Description'),
            ]
        );
    }

    /**
     * PackagedProductPackagingChild class (formerly FieldCollection)
     * A simplified version of Packaging for safe recursion.
     * @throws Exception
     */
    public function createPackagedProductPackagingChildClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'PackagedProductPackagingChild',
            'FHIR 6.0.0 Inner Packaging',
            [
                $this->fieldHelpers->createObjectRelationField('identifier','Identifier(s)',['Identifier'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('packagingType','Type',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('quantity','Quantity',['Quantity'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('material','Material(s)',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('alternateMaterial','Alternate Material(s)',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('shelfLifeStorage','Shelf Life / Storage',['ProductShelfLife'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('manufacturer','Manufacturer(s)',['Reference'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('characteristic','Characteristic(s)',['PackagedProductCharacteristic'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('containedItem','Contained Item(s)',['PackagedProductPackagingContainedItem'], '', false, true),
                $this->fieldHelpers->createTextareaField('description','Description'),
            ]
        );
    }

    /**
     * PackagedProductPackagingContainedItem class (formerly FieldCollection)
     * @throws Exception
     */
    public function createPackagedProductPackagingContainedItemClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'PackagedProductPackagingContainedItem',
            'FHIR 6.0.0 Contained Item',
            [
                $this->fieldHelpers->createObjectRelationField('itemCodeableConcept','Item (CodeableConcept)',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('itemReference','Item (Reference)',['Reference'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('amountQuantity','Amount (Quantity)',['Quantity'], '', false, false),
                $this->fieldHelpers->createNumericField('amountInteger','Amount (Integer)','Simple integer amount.'),
                $this->fieldHelpers->createObjectRelationField('amountRatio','Amount (Ratio)',['Ratio'], '', false, false),
            ]
        );
    }

    /**
     * PackagedProductCharacteristic class (formerly FieldCollection)
     * @throws Exception
     */
    public function createPackagedProductCharacteristicClass(SymfonyStyle $io): void
    {
        $this->fieldHelpers->upsertClass($io,
            'PackagedProductCharacteristic',
            'FHIR 6.0.0 Characteristic',
            [
                $this->fieldHelpers->createObjectRelationField('code','Code',['CodeableConcept'],'Characteristic kind (e.g., color).', true, false),
                $this->fieldHelpers->createObjectRelationField('status','Status',['CodeableConcept'],'Regulatory / availability status.', false, false),
                $this->fieldHelpers->createObjectRelationField('valueCodeableConcept','Value (CodeableConcept)',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('valueQuantity','Value (Quantity)',['Quantity'], '', false, false),
                $this->fieldHelpers->createInputField('valueString','Value (String)','', false, 400),
                $this->fieldHelpers->createTextareaField('valueMarkdown','Value (Markdown)'),
                $this->fieldHelpers->createCheckboxField('valueBoolean','Value (Boolean)'),
                $this->fieldHelpers->createDatetimeField('valueDate','Value (Date)'),
                $this->fieldHelpers->createObjectRelationField('valueAttachment','Value (Attachment)',['Attachment'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('valueReference','Value (Reference)',['Reference'], '', false, false),
            ]
        );
    }

    /**
     * SubstanceMolecularWeight class (formerly FieldCollection)
     * @throws Exception
     */
    public function createSubstanceMolecularWeightClass(SymfonyStyle $io): void
    {
        $this->upsertClass($io,
            'SubstanceMolecularWeight',
            'FHIR 6.0.0 Molecular Weight',
            [
                $this->fieldHelpers->createObjectRelationField('method','Method',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('weightType','Type',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('amount','Amount',['Quantity'], '', false, false),
            ]
        );
    }

    /**
     * SubstanceSourceMaterial class (formerly FieldCollection)
     * @throws Exception
     */
    public function createSubstanceSourceMaterialClass(SymfonyStyle $io): void
    {
        $this->upsertClass($io,
            'SubstanceSourceMaterial',
            'FHIR 6.0.0 Source Material',
            [
                $this->fieldHelpers->createObjectRelationField('sourceType','Type',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('genus','Genus',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('species','Species',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('part','Part',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('countryOfOrigin','Country Of Origin',['CodeableConcept'], '', false, true),
            ]
        );
    }

    /**
     * SubstanceCharacterization class (formerly FieldCollection)
     * @throws Exception
     */
    public function createSubstanceCharacterizationClass(SymfonyStyle $io): void
    {
        $this->upsertClass($io,
            'SubstanceCharacterization',
            'FHIR 6.0.0 Characterization',
            [
                $this->fieldHelpers->createObjectRelationField('technique','Technique',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('form','Form',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createTextareaField('description','Description'),
                $this->fieldHelpers->createObjectRelationField('file','File',['Attachment'], '', false, true),
            ]
        );
    }

    /**
     * @throws Exception
     */
    public function createSubstanceClass(SymfonyStyle $io): void
    {
        $this->upsertClass(
            $io,
            'Substance',
            'FHIR 6.0.0 Substance Resource',
            [
                $this->fieldHelpers->createObjectRelationField('identifier','Identifier',['Identifier'], '', false, true),
                $this->fieldHelpers->createInputField('version','Version','',false,100),
                $this->fieldHelpers->createObjectRelationField('status','Status',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('classification','Classification',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('domain','Domain',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('grade','Grade',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createTextareaField('description','Description'),
                $this->fieldHelpers->createObjectRelationField('informationSource','Information Source',['Reference'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('note','Note',['Annotation'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('manufacturer','Manufacturer',['Reference'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('supplier','Supplier',['Reference'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('moiety','Moiety',['SubstanceMoiety'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('characterization','Characterization',['SubstanceCharacterization'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('propertyDetail','Property',['SubstanceProperty'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('referenceInformation','Reference Information',['Reference'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('molecularWeight','Molecular Weight',['SubstanceMolecularWeight'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('structure','Structure',['SubstanceStructure'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('code','Code',['SubstanceCode'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('name','Name',['SubstanceName'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('relationship','Relationship',['SubstanceRelationship'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('nucleicAcid','Nucleic Acid',['Reference'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('polymer','Polymer',['Reference'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('protein','Protein',['Reference'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('sourceMaterial','Source Material',['SubstanceSourceMaterial'], '', false, false),
            ]
        );
    }

    /**
     * @throws Exception
     */
    public function createMedicinalProductClass(SymfonyStyle $io): void
    {
        $this->upsertClass(
            $io,
            'MedicinalProduct',
            'FHIR 6.0.0 MedicinalProduct Resource',
            [
                $this->fieldHelpers->createObjectRelationField('identifier','Identifiers',['Identifier'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('medicinalProductType','Type',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createInputField('version','Version','',false,200),
                $this->fieldHelpers->createObjectRelationField('domain','Domain',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('status','Status',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createDatetimeField('statusDate','Status Date'),
                $this->fieldHelpers->createInputField('description','Description','',false,600),
                $this->fieldHelpers->createObjectRelationField('combinedPharmaceuticalDoseForm','Combined Pharmaceutical Dose Form',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('route','Route',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('indication','Indication',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('legalStatusOfSupply','Legal Status of Supply',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('additionalMonitoringIndicator','Additional Monitoring Indicator',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('specialMeasures','Special Measures',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('pediatricUseIndicator','Pediatric Use Indicator',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('classification','Classification',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('marketingStatus','Marketing Status',['MarketingStatus'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('packagedMedicinalProduct','Packaged Medicinal Product',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('comprisedOf','Comprised Of',['Identifier'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('ingredient','Ingredient',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('impurity','Impurity',['Reference'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('attachedDocument','Attached Document',['Attachment'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('masterFile','Master File',['Attachment'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('contact','Contact',['ContactDetail'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('clinicalTrial','Clinical Trial',['Reference'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('code','Code',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('name','Name',['MedicinalProductName'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('characteristic','Characteristic',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('productShelfLife','Product Shelf Life',['ProductShelfLife'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('manufacturingBusinessOperation','Manufacturing Business Operation',['ManufacturingBusinessOperation'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('medicinalProductProperty','Property',['MedicinalProductProperty'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('crossReference','Cross Reference',['MedicinalProductCrossReference'], '', false, true),
            ]
        );
    }

    /**
     * @throws Exception
     */
    public function createRegulatedAuthorizationClass(SymfonyStyle $io): void
    {
        $this->upsertClass(
            $io,
            'RegulatedAuthorization',
            'FHIR 6.0.0 RegulatedAuthorization Resource',
            [
                $this->fieldHelpers->createObjectRelationField('identifier','Identifiers',['Identifier'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('subject','Subject',['Reference'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('authorizationType','Authorization Type',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createInputField('description','Description','',false,600),
                $this->fieldHelpers->createObjectRelationField('region','Region(s)',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('status','Status',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createDatetimeField('statusDate','Status Date'),
                $this->fieldHelpers->createObjectRelationField('validityPeriod','Validity Period',['Period'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('indication','Indication(s)',['Reference'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('intendedUse','Intended Use',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('basis','Basis',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('holder','Holder',['Reference'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('regulator','Regulator',['Reference'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('attachedDocument','Attached Document(s)',['Reference'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('case','Case(s)',['RegulatedAuthorizationCase'], '', false, true),
            ]
        );
    }

    /**
     * @throws Exception
     */
    public function createPackagedProductClass(SymfonyStyle $io): void
    {
        $this->upsertClass(
            $io,
            'PackagedProduct',
            'FHIR 6.0.0 PackagedProduct Resource',
            [
                $this->fieldHelpers->createObjectRelationField('identifier','Identifiers',['Identifier'], '', false, true),
                $this->fieldHelpers->createInputField('name','Name','',false,300),
                $this->fieldHelpers->createObjectRelationField('productType','Type',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('packageFor','Package For',['Reference'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('status','Status',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createDatetimeField('statusDate','Status Date'),
                $this->fieldHelpers->createObjectRelationField('containedItemQuantity','Contained Item Quantity',['Quantity'], '', false, true),
                $this->fieldHelpers->createInputField('description','Description','',false,600),
                $this->fieldHelpers->createObjectRelationField('legalStatusOfSupply','Legal Status of Supply',['PackagedProductLegalStatusOfSupply'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('marketingStatus','Marketing Status',['MarketingStatus'], '', false, true),
                $this->fieldHelpers->createCheckboxField('copackagedIndicator','Copackaged Indicator'),
                $this->fieldHelpers->createObjectRelationField('manufacturer','Manufacturer(s)',['Reference'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('attachedDocument','Attached Document(s)',['Reference'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('packaging','Packaging',['PackagedProductPackaging'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('characteristic','Characteristic(s)',['PackagedProductCharacteristic'], '', false, true),
            ]
        );
    }

    /**
     * @throws Exception
     */
    public function createManufacturedItemClass(SymfonyStyle $io): void
    {
        $this->upsertClass(
            $io,
            'ManufacturedItem',
            'FHIR 6.0.0 ManufacturedItem Resource',
            [
                $this->fieldHelpers->createObjectRelationField('identifier','Identifiers',['Identifier'], '', false, true),
                $this->fieldHelpers->createInputField('status','Status','draft | active | retired | unknown',false,80),
                $this->fieldHelpers->createInputField('name','Name','',false,300),
                $this->fieldHelpers->createObjectRelationField('manufacturedDoseForm','Manufactured Dose Form',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('unitOfPresentation','Unit of Presentation',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('manufacturer','Manufacturer(s)',['Reference'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('marketingStatus','Marketing Status',['MarketingStatus'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('ingredient','Ingredient(s)',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('propertyDetail','Property',['ManufacturedItemProperty'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('component','Component(s)',['ManufacturedItemComponent'], '', false, true),
            ]
        );
    }

    /**
     * @throws Exception
     */
    public function createAdministrableProductClass(SymfonyStyle $io): void
    {
        $this->upsertClass(
            $io,
            'AdministrableProduct',
            'FHIR 6.0.0 AdministrableProduct Resource',
            [
                $this->fieldHelpers->createObjectRelationField('identifier','Identifiers',['Identifier'], '', false, true),
                $this->fieldHelpers->createInputField('status','Status','draft | active | retired | unknown',false,80),
                $this->fieldHelpers->createObjectRelationField('formOf','Form Of',['Reference'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('administrableDoseForm','Administrable Dose Form',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('unitOfPresentation','Unit of Presentation',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('producedFrom','Produced From',['Reference'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('ingredient','Ingredient(s)',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('device','Device',['Reference'], '', false, false),
                $this->fieldHelpers->createInputField('description','Description','',false,600),
                $this->fieldHelpers->createObjectRelationField('propertyDetail','Property',['AdministrableProductProperty'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('routeOfAdministration','Route of Administration',['APDRouteOfAdmin'], '', false, true),
            ]
        );
    }

    /**
     * @throws Exception
     */
    public function createIngredientClass(SymfonyStyle $io): void
    {
        $this->upsertClass(
            $io,
            'Ingredient',
            'FHIR 6.0.0 Ingredient Resource',
            [
                $this->fieldHelpers->createObjectRelationField('identifier','Identifier',['Identifier'], '', false, true),
                $this->fieldHelpers->createInputField('status','Status','draft | active | retired | unknown',false,80),
                $this->fieldHelpers->createObjectRelationField('for','For (Constituent Of)',['Reference'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('role','Role',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('function','Function',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('group','Group',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createCheckboxField('allergenicIndicator','Allergenic Indicator'),
                $this->fieldHelpers->createTextareaField('comment','Comment'),
                $this->fieldHelpers->createObjectRelationField('manufacturer','Manufacturer(s)',['IngredientManufacturer'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('substance','Substance',['IngredientSubstance'], '', false, true),
            ]
        );
    }

    /**
     * @throws Exception
     */
    public function createClinicalUseClass(SymfonyStyle $io): void
    {
        $this->upsertClass(
            $io,
            'ClinicalUse',
            'FHIR 6.0.0 ClinicalUse Resource',
            [
                $this->fieldHelpers->createObjectRelationField('identifier','Identifiers',['Identifier'], '', false, true),
                $this->fieldHelpers->createInputField('clinicalUseType','Type','indication | contraindication | interaction | undesirable-effect | warning',false,120),
                $this->fieldHelpers->createObjectRelationField('category','Category',['CodeableConcept'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('subject','Subject',['Reference'], '', false, true),
                $this->fieldHelpers->createObjectRelationField('status','Status',['CodeableConcept'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('contraindication','Contraindication',['ClinicalUseContraindication'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('indication','Indication',['ClinicalUseIndication'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('interaction','Interaction',['ClinicalUseInteraction'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('population','Population',['Reference'], '', false, true),
                $this->fieldHelpers->createTextareaField('library','Library'),
                $this->fieldHelpers->createObjectRelationField('undesirableEffect','Undesirable Effect',['ClinicalUseUndesirableEffect'], '', false, false),
                $this->fieldHelpers->createObjectRelationField('warning','Warning',['ClinicalUseWarning'], '', false, false),
            ]
        );
    }
}
