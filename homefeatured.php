<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Business\Module\WidgetInterface;
use PrestaShop\PrestaShop\Adapter\Category\CategoryProductSearchProvider;
use PrestaShop\PrestaShop\Adapter\Translator;
use PrestaShop\PrestaShop\Adapter\LegacyContext;
use PrestaShop\PrestaShop\Core\Business\Product\Search\ProductSearchContext;
use PrestaShop\PrestaShop\Core\Business\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Business\Product\Search\SortOrder;

class homefeatured extends Module implements WidgetInterface
{
    public function __construct()
    {
        $this->name = 'homefeatured';
        $this->tab = 'front_office_features';
        $this->version = '2.0.0';
        $this->author = 'PrestaShop';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7',
            'max' => _PS_VERSION_,
        ];

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Featured products on the homepage');
        $this->description = $this->l('Displays featured products in the central column of your homepage.');
    }

    public function install()
    {
        $this->_clearCache('*');
        Configuration::updateValue('HOME_FEATURED_NBR', 8);
        Configuration::updateValue('HOME_FEATURED_CAT', (int) Context::getContext()->shop->getCategory());
        Configuration::updateValue('HOME_FEATURED_RANDOMIZE', false);

        return parent::install()
            && $this->registerHook('addproduct')
            && $this->registerHook('updateproduct')
            && $this->registerHook('deleteproduct')
            && $this->registerHook('categoryUpdate')
            && $this->registerHook('displayHome')
        ;
    }

    public function uninstall()
    {
        $this->_clearCache('*');

        return parent::uninstall();
    }

    public function getContent()
    {
        $output = '';
        $errors = array();
        if (Tools::isSubmit('submitHomeFeatured')) {
            $nbr = Tools::getValue('HOME_FEATURED_NBR');
            if (!Validate::isInt($nbr) || $nbr <= 0) {
                $errors[] = $this->l('The number of products is invalid. Please enter a positive number.');
            }

            $cat = Tools::getValue('HOME_FEATURED_CAT');
            if (!Validate::isInt($cat) || $cat <= 0) {
                $errors[] = $this->l('The category ID is invalid. Please choose an existing category ID.');
            }

            $rand = Tools::getValue('HOME_FEATURED_RANDOMIZE');
            if (!Validate::isBool($rand)) {
                $errors[] = $this->l('Invalid value for the "randomize" flag.');
            }
            if (isset($errors) && count($errors)) {
                $output = $this->displayError(implode('<br />', $errors));
            } else {
                Configuration::updateValue('HOME_FEATURED_NBR', (int) $nbr);
                Configuration::updateValue('HOME_FEATURED_CAT', (int) $cat);
                Configuration::updateValue('HOME_FEATURED_RANDOMIZE', (bool) $rand);
                Tools::clearCache(Context::getContext()->smarty, $this->getTemplatePath('homefeatured.tpl'));
                $output = $this->displayConfirmation($this->l('Your settings have been updated.'));
            }
        }

        return $output.$this->renderForm();
    }

    public function getProducts()
    {
        $category = new Category((int) Configuration::get('HOME_FEATURED_CAT'));

        $searchProvider = new CategoryProductSearchProvider(
            new Translator(new LegacyContext()),
            $category
        );

        $context = new ProductSearchContext($this->context);

        $query = new ProductSearchQuery();

        $nProducts = Configuration::get('HOME_FEATURED_NBR');
        if ($nProducts < 0) {
            $nProducts = 12;
        }

        $query
            ->setResultsPerPage($nProducts)
            ->setPage(1)
        ;

        if (Configuration::get('HOME_FEATURED_RANDOMIZE')) {
            $query->setSortOrder(SortOrder::random());
        } else {
            $query->setSortOrder(new SortOrder('product', 'position', 'asc'));
        }

        $result = $searchProvider->runQuery(
            $context,
            $query
        );

        $assembler = new ProductAssembler($this->context);

        $presenterFactory = new ProductPresenterFactory($this->context);
        $presentationSettings = $presenterFactory->getPresentationSettings();
        $presenter = $presenterFactory->getPresenter();

        $products_for_template = [];

        foreach ($result->getProducts() as $rawProduct) {
            $products_for_template[] = $presenter->presentForListing(
                $presentationSettings,
                $assembler->assembleProduct($rawProduct),
                $this->context->language
            );
        }

        return $products_for_template;
    }

    public function hookAddProduct($params)
    {
        $this->_clearCache('*');
    }

    public function hookUpdateProduct($params)
    {
        $this->_clearCache('*');
    }

    public function hookDeleteProduct($params)
    {
        $this->_clearCache('*');
    }

    public function hookCategoryUpdate($params)
    {
        $this->_clearCache('*');
    }

    public function _clearCache($template, $cache_id = null, $compile_id = null)
    {
        parent::_clearCache('homefeatured.tpl', 'homefeatured');
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'description' => $this->l('To add products to your homepage, simply add them to the corresponding product category (default: "Home").'),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Number of products to be displayed'),
                        'name' => 'HOME_FEATURED_NBR',
                        'class' => 'fixed-width-xs',
                        'desc' => $this->l('Set the number of products that you would like to display on homepage (default: 8).'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Category from which to pick products to be displayed'),
                        'name' => 'HOME_FEATURED_CAT',
                        'class' => 'fixed-width-xs',
                        'desc' => $this->l('Choose the category ID of the products that you would like to display on homepage (default: 2 for "Home").'),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Randomly display featured products'),
                        'name' => 'HOME_FEATURED_RANDOMIZE',
                        'class' => 'fixed-width-xs',
                        'desc' => $this->l('Enable if you wish the products to be displayed randomly (default: no).'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ),
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $this->fields_form = array();
        $helper->id = (int) Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitHomeFeatured';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'HOME_FEATURED_NBR' => Tools::getValue('HOME_FEATURED_NBR', (int) Configuration::get('HOME_FEATURED_NBR')),
            'HOME_FEATURED_CAT' => Tools::getValue('HOME_FEATURED_CAT', (int) Configuration::get('HOME_FEATURED_CAT')),
            'HOME_FEATURED_RANDOMIZE' => Tools::getValue('HOME_FEATURED_RANDOMIZE', (bool) Configuration::get('HOME_FEATURED_RANDOMIZE')),
        );
    }

    public function getWidgetVariables($hookName = null, array $configuration = [])
    {
        return [
            'products' => $this->getProducts(),
        ];
    }

    public function renderWidget($hookName = null, array $configuration = [])
    {
        if (!$this->isCached('homefeatured.tpl', $this->getCacheId('homefeatured'))) {
            $this->smarty->assign($this->getWidgetVariables($hookName, $configuration));
        }

        return $this->display(__FILE__, 'homefeatured.tpl', $this->getCacheId('homefeatured'));
    }
}
