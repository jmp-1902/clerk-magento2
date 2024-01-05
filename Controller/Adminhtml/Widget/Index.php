<?php

namespace Clerk\Clerk\Controller\Adminhtml\Widget;

use Clerk\Clerk\Controller\Logger\ClerkLogger;
use Clerk\Clerk\Model\Api;
use Clerk\Clerk\Model\Config\Source\Content;
use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Block\Widget\Form\Renderer\Fieldset as FieldSet;
use Magento\Backend\Block\Widget\Form\Renderer\Fieldset\Element as FieldElement;
use Magento\Catalog\Block\Adminhtml\Product\Widget\Chooser as WidgetChooser;
use Magento\Framework\Data\Form\Element\Select as FormSelect;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Option\ArrayPool;

class Index extends Action
{
    /**
     * @var ClerkLogger
     */
    protected ClerkLogger $clerkLogger;

    /**
     * @var FieldSet
     */
    protected FieldSet $fieldSet;

    /**
     * @var WidgetChooser
     */
    protected WidgetChooser $widgetChooser;

    /**
     * @var FieldElement
     */
    protected FieldElement $fieldElement;

    /**
     * @var FormSelect
     */
    protected FormSelect $formSelect;

    /**
     * @var Api
     */
    protected Api $api;

    /**
     * @var FormFactory
     */
    protected FormFactory $formFactory;

    /**
     * @var ArrayPool
     */
    protected ArrayPool $sourceModelPool;

    /**
     * Index constructor.
     *
     * @param Context $context
     * @param Api $api
     * @param FormFactory $formFactory
     * @param FormSelect $formSelect
     * @param FieldElement $fieldElement
     * @param FieldSet $fieldSet
     * @param WidgetChooser $widgetChooser
     * @param ArrayPool $sourceModelPool
     * @param ClerkLogger $clerk_logger
     */
    public function __construct(
        Action\Context $context,
        Api            $api,
        FormFactory    $formFactory,
        FormSelect     $formSelect,
        FieldElement   $fieldElement,
        FieldSet       $fieldSet,
        WidgetChooser  $widgetChooser,
        ArrayPool      $sourceModelPool,
        ClerkLogger    $clerk_logger
    )
    {
        $this->api = $api;
        $this->formFactory = $formFactory;
        $this->sourceModelPool = $sourceModelPool;
        $this->clerkLogger = $clerk_logger;
        $this->formSelect = $formSelect;
        $this->fieldElement = $fieldElement;
        $this->fieldSet = $fieldSet;
        $this->widgetChooser = $widgetChooser;

        parent::__construct($context);
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function execute(): void
    {
        try {

            $type = $this->getRequest()->getParam('type', 'content');

            switch ($type) {
                case 'content':
                    $this->getContentResponse();
                    break;
                case 'parameters':
                    $this->getParametersResponse();
                    break;
                default:
                    $this->getInvalidResponse();
            }
        } catch (Exception $e) {

            $this->clerkLogger->error('Widget execute ERROR', ['error' => $e->getMessage()]);

        }
    }

    /**
     * @throws FileSystemException
     */
    public function getContentResponse(): void
    {
        try {
            $form = $this->formFactory->create();
            $select = $this->formSelect;
            $select->setHtmlId('clerk_widget_content');
            $select->setId('clerk_widget_content');
            $select->setCssClass('clerk_content_select');
            $select->setName('parameters[content]');
            $select->setValues($this->sourceModelPool->get(Content::class)->toOptionArray());
            $select->setLabel(__('Content'));
            $select->setForm($form);

            $renderer = $this->fieldElement;

            $this->getResponse()
                ->setHttpResponseCode(200)
                ->setHeader('Content-Type', 'application/json', true)
                ->representJson(
                    json_encode([
                        'success' => true,
                        'content' => $renderer->render($select)
                    ])
                );

        } catch (Exception $e) {

            $this->clerkLogger->error('Widget getContentResponse ERROR', ['error' => $e->getMessage()]);

        }
    }

    /**
     * @throws FileSystemException
     */
    public function getParametersResponse(): void
    {
        try {

            $content = $this->getRequest()->getParam('content');

            $endpoint = $this->api->getEndpointForContent($content);
            $parameters = $this->api->getParametersForEndpoint($endpoint);

            $html = '';

            if (!!array_intersect(['products', 'category'], $parameters)) {
                $form = $this->formFactory->create();
                $form->setFieldsetRenderer($this->fieldSet);
                $form->setUseContainer(false);

                $fieldset = $form->addFieldset('clerk_widget_options', [
                    'legend' => __('Clerk Content Options'),
                    'class' => 'fieldset-wide fieldset-widget-options clerk_widget_parameters',
                ]);

                if (in_array('products', $parameters)) {
                    $label = $fieldset->addField('product_id', 'label', [
                        'name' => $form->addSuffixToName('product_id', 'parameters'),
                        'class' => 'widget-option',
                        'label' => __('Product')
                    ]);

                    $chooser = $this->widgetChooser;
                    $chooser->setHtmlId('clerk_widget_content');
                    $chooser->setConfig([
                        'button' => [
                            'open' => __('Select Product...')
                        ]
                    ]);
                    $chooser->setId('clerk_widget_content');
                    $chooser->setElement($label);
                    $chooser->setFieldsetId('clerk_widget_options');
                    $chooser->setCssClass('clerk_content_select');
                    $chooser->setName('parameters[content]');
                    $chooser->setLabel(__('Content'));
                    $chooser->setForm($form);

                    $chooser->prepareElementHtml($label);
                }

                if (in_array('category', $parameters)) {
                    $label = $fieldset->addField('category_id', 'label', [
                        'name' => $form->addSuffixToName('category_id', 'parameters'),
                        'class' => 'widget-option',
                        'label' => __('Category')
                    ]);

                    $chooser = $this->widgetChooser;
                    $chooser->setHtmlId('clerk_widget_content');
                    $chooser->setConfig([
                        'button' => [
                            'open' => __('Select Category...')
                        ]
                    ]);
                    $chooser->setId('clerk_widget_content');
                    $chooser->setElement($label);
                    $chooser->setFieldsetId('clerk_widget_options');
                    $chooser->setCssClass('clerk_content_select');
                    $chooser->setName('parameters[content]');
                    $chooser->setLabel(__('Content'));
                    $chooser->setForm($form);

                    $chooser->prepareElementHtml($label);
                }

                $html .= $form->toHtml();
            }

            $this->getResponse()
                ->setHttpResponseCode(200)
                ->setHeader('Content-Type', 'application/json', true)
                ->representJson(
                    json_encode([
                        'success' => true,
                        'content' => $html
                    ])
                );

        } catch (Exception $e) {

            $this->clerkLogger->error('Widget getParametersResponse ERROR', ['error' => $e->getMessage()]);

        }
    }

    /**
     * @return void
     * @throws FileSystemException
     */
    public function getInvalidResponse(): void
    {
        try {

            $this->getResponse()
                ->setHttpResponseCode(422)
                ->setHeader('Content-Type', 'application/json', true)
                ->representJson(
                    json_encode([
                        'success' => false,
                        'content' => 'invalid type'
                    ])
                );

        } catch (Exception $e) {

            $this->clerkLogger->error('Widget getInvalidResponse ERROR', ['error' => $e->getMessage()]);

        }
    }
}
