<?php
/**
 * @author Drubu Team
 * @copyright Copyright (c) 2021 Drubu
 * @package Iflow_IflowShipping
 */

namespace Iflow\IflowShipping\Block\System\Config;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

class Attributes extends AbstractFieldArray
{

    const TEMPLATE = 'Iflow_IflowShipping::system/config/attributes.phtml';

    /**
     * @var string
     */
    protected $_template = self::TEMPLATE;

    /**
     * @var \Magento\Eav\Model\Attribute
     */
    protected $attributeCollection;

    public function __construct(
        \Magento\Eav\Model\Attribute $attributeCollection,
        \Magento\Backend\Block\Template\Context $context,
        array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->attributeCollection = $attributeCollection;
    }

    /**
     * Prepare rendering the new field by adding all the needed columns
     */
    protected function _prepareToRender()
    {
        $this->addColumn('field', ['label' => __('Field'), 'class' => 'required-entry']);
        $this->addColumn('attribute', ['label' => __('Attribute'), 'class' => 'required-entry']);
        $this->addColumn('array_position', ['label' => __('Array position'), 'class' => '']);
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add');
    }

    /**
     * Prepare existing row data object
     *
     * @param DataObject $row
     * @throws LocalizedException
     */
    protected function _prepareArrayRow(DataObject $row): void
    {
        $options = [];
        $row->setData('option_extra_attrs', $options);
    }

    public function getAddressFields(){
        return [
            ['label' => 'Calle', 'value' => 'street'],
            ['label' => 'Numero', 'value' => 'number'],
            ['label' => 'Piso', 'value' => 'floor'],
            ['label' => 'Departamento', 'value' => 'apartment'],
            ['label' => 'Notas adicionales', 'value' => 'additional_notes'],
        ];
    }

    public function getAddressFieldsOptions(){
        $attributes = $this->attributeCollection->getCollection();
        $attributes
            ->addFieldToFilter("entity_type_id", "2");
        $options = '<option value="" data-type="">Seleccione una opcion</option>';
        foreach ($attributes as $attribute){
            $options .= '<option value="' . $attribute->getAttributeCode() .'" data-type="' . $attribute->getFrontendInput() .  '">' . $attribute->getAttributeCode() . '</option>';
        }
        return $options;
    }
}
