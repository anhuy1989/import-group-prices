<?php

class Anhi_ImportGroupPrices_Adminhtml_ImportController extends Mage_Adminhtml_Controller_Action
{

    protected $_errors = array(
        0=>"There is no error, the file uploaded with success",
        1=>"The uploaded file exceeds the upload_max_filesize directive in php.ini",
        2=>"The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form",
        3=>"The uploaded file was only partially uploaded",
        4=>"No file was uploaded",
        6=>"Missing a temporary folder"
    );
    protected function _initAction()
    {
        $this->_title($this->__('Import/Export'))
            ->loadLayout()
            ->_setActiveMenu('system/importexport');

        return $this;
    }
    public function indexAction()
    {
        $this->loadLayout();
        $this->_initAction()
            ->_title($this->__('Import Custom Tool'))
            ->_addBreadcrumb($this->__('Import'), $this->__('Import Custom Tool'));
        if($_FILES){
            if ($_FILES["file"]["error"] > 0){
                Mage::getSingleton('core/session')->addError($this->_errors[$_FILES["file"]["error"]]);
            } else {
                $method = "import_".$this->getRequest()->getParam('hidden_data');
                if(method_exists($this, $method)){
                    call_user_func_array(array(&$this, $method), array());
                    Mage::getSingleton('core/session')->addSuccess("Import Complete!");
                } else {
                    Mage::getSingleton('core/session')->addError('Import method not found: "'.$method.'"');
                }
            }
            $this->_initLayoutMessages('core/session');
        }
        $this->renderLayout();
    }

    protected function import_group_price()
    {
        $all_groups = false;
        $insert = Mage::getSingleton('core/resource')->getConnection('core_write');
        $file = fopen($_FILES['file']['tmp_name'], "r");
        $columns = array();
        while ($data = fgetcsv($file, 2000, ",")) {
            if(!$columns){
                $columns = $data;
            } else {
                $sku = $data[0];
                $website_id = $data[1];
                $customer_group_id = $data[2];
                $value = $data[3];
                $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);
                if($product && $product->getId()){
                    $entity_id = $product->getId();
                    $sql = "
                        INSERT INTO catalog_product_entity_group_price (entity_id, all_groups, customer_group_id, value, website_id)
                        VALUES ('$entity_id', '$all_groups', '$customer_group_id', '$value', '$website_id')
                        ON DUPLICATE KEY UPDATE
                            entity_id = '$entity_id',
                            all_groups = '$all_groups',
                            customer_group_id = '$customer_group_id',
                            value = '$value',
                            website_id = '$website_id';
                    ";
                    $insert->query($sql);
                }
                else{
                    Mage::getSingleton('core/session')->addError($sku.' is not existed!');
                }
            }
        }
        $sql = "UPDATE `index_process` SET `status` = 'require_reindex' WHERE indexer_code='catalog_product_price'";
        $insert->query($sql);
    }


}