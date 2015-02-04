<?php

namespace ride\web\cms\controller\widget;

use ride\library\cms\node\NodeModel;
use ride\library\validation\exception\ValidationException;

use \Mailchimp;

/**
 * Newsletter form for Seforis homepage
 */
class MailchimpSubscribeWidget extends AbstractWidget implements StyleWidget {

    /**
     * Machine name for this widget
     * @var string
     */
    const NAME  = 'mailchimp.subscribe';

    /**
     * Path to the icon of this widget
     * @var string
     */
    const ICON  = 'img/cms/widget/mailchimp.subscribe.png';

    /**
     * Template resource for this widget
     * @var string
     */
    const TEMPLATE_NAMESPACE = 'cms/widget/mailchimp';

    /**
     * Action to show and handle the subscribe form
     * @return null
     */
    public function indexAction() {
        $apiKey = $this->properties->getWidgetProperty('apikey');
        $listId = $this->properties->getWidgetProperty('listid');
        if (!$apiKey || !$listId) {
            return;
        }

        $parameters = $this->request->getQueryParameters();
        $translator = $this->getTranslator();
        $mailchimp = new Mailchimp($apiKey);

        $fields = $this->getListVariables($mailchimp, $listId);
        $field_typeMapper = array(
            'email' => 'email',
            'text' => 'string',
            'date' => 'date',
        );

        $form = $this->createFormBuilder($parameters);

        foreach ($fields as $field) {
            if ($field['show']) {
                if ($field['field_type'] == 'text' || $field['field_type'] == 'email') {
                    $attr = array(
                        'label' => $translator->translate('label.mailchimp.' . strtolower($field['name'])),
                    );
                    if (isset($parameters[$field['tag']])) {

                        $attr['value'] = $parameters[$field['tag']];
                    }
                    if ($field['req']) {
                        $attr['validators'] = array(
                            'required' => array()
                        );
                    }
                    $field_type = $field_typeMapper[$field['field_type']];

                    $form->addRow($field['tag'], $field_type, $attr);

                }
            }
        }

        $form = $form->build();

        if ($form->isSubmitted()) {
            try {

                $form->validate();

                $data = $form->getData();
                $email = array('email' => $data['EMAIL']);
                unset($data['email']);

                $variables = null;
                if($data) {
                    $variables = array();
                    foreach ($data as $key => $parameter) {
                        $variable = [];
                        $variable[$key] = $parameter;

                        $variables[$key] = $parameter;
                    }

                    $variables = array('merge_vars' => $variables);
                }


                $response = $mailchimp->lists->memberInfo($listId, array($email));

                if (isset($response['errors']['0']['code'])) {
                    $code = $response['errors']['0']['code'];
                    switch ($code) {
                        case 230:
                            $this->addError('warning.mailchimp.email.exists');

                            break;
                        case 231:
                        case 232:
                        case 233:
                            $mailchimp->lists->subscribe($listId, $email, $variables['merge_vars'], 'html', false, true, false, $code == 232);

                            $finish = $this->properties->getWidgetProperty('finish.node');
                            if ($finish) {
                                $url = $this->getUrl('cms.front.' . $this->properties->getNode()->getRootNodeId() . '.' . $finish . '.' . $this->locale);
                            } else {

                                $filterUrl = str_replace('?' . $this->request->getQueryParametersAsString(), '', $this->request->getUrl());
                                $url = $filterUrl;

                                $this->addSuccess('success.mailchimp.subscribe');
                                $parameters = null;
                            }

                            $this->response->setRedirect($url);

                            return;

                            break;
                        default:
                            $this->addWarning('error.mailchimp.subscribe.general', array('error' => $response['errors']['0']['message']));

                            break;
                    }
                } else {
                    $this->addError('error.mailchimp.subscribe.unknown');
                }
            } catch (ValidationException $exception) {
                $this->setValidationException($exception, $form);
            }
        }

        $this->setTemplateView($this->getTemplate(static::TEMPLATE_NAMESPACE . '/default'), array(
            'title' => $this->properties->getWidgetProperty('title.' . $this->locale),
            'form' => $form->getView(),
            'parameters' => $parameters
        ));
    }

    /**
     * Gets the preview of the properties of this widget instance
     * @return string
     */
    public function getPropertiesPreview() {
        $translator = $this->getTranslator();

        $preview = '';

        $title = $this->properties->getWidgetProperty('title.' . $this->locale);
        if ($title) {
            $preview .= '<strong>' . $translator->translate('label.title') .'</strong> ' . $title. '<br/>';
        }

        $apiKey = $this->properties->getWidgetProperty('apikey');
        if ($apiKey) {
            $preview .= '<strong>' . $translator->translate('label.key.api') .'</strong> ' . $apiKey . '<br/>';
        }

        $listId = $this->properties->getWidgetProperty('listid');
        if ($listId) {
            $preview .= '<strong>' . $translator->translate('label.id.list') . '</strong> ' . $listId . '<br/>';
        }

        return $preview;
    }

    /**
     * Action to setup the properties of this widget
     * @return null
     */
    public function propertiesAction(NodeModel $nodeModel) {
        $translator = $this->getTranslator();

        if ($this->properties->getWidgetProperty('apikey') && $this->properties->getWidgetProperty('listid')) {
            $apiKey = $this->properties->getWidgetProperty('apikey');
            $listId = $this->properties->getWidgetProperty('listid');
            $mailChimp = new Mailchimp($apiKey);

            $list_vars = $mailChimp->lists->mergeVars(array($listId));
            $list_vars = $list_vars['data'][0]['merge_vars'];
        }

        $data = $this->properties->getWidgetProperties();

        $form = $this->createFormBuilder($data);
        $form->addRow('title.'.$this->locale, 'string', array(
           'label' => $translator->translate('label.title'),
        ));
        $form->addRow('apikey', 'string', array(
           'label' => $translator->translate('label.key.api'),
           'description' => $translator->translate('label.key.api.mailchimp.description')
        ));
        $form->addRow('listid', 'string', array(
            'label' => $translator->translate('label.id.list'),
            'description' => $translator->translate('label.id.list.mailchimp.description')
        ));
        $form->addRow(self::PROPERTY_TEMPLATE, 'select', array(
            'label' => $translator->translate('label.template'),
            'options' => $this->getAvailableTemplates(static::TEMPLATE_NAMESPACE),
            'validators' => array(
                'required' => array(),
            ),
        ));
        $form->addRow('finishNode', 'select', array(
                    'label' => $translator->translate('label.node.finish'),
                    'description' => $translator->translate('label.node.finish.description'),
                    'options' => $this->getNodeList($nodeModel),
                ));

        foreach ($list_vars as $var) {
            $show = $var['show'];
            $required = $var['req'];
            $attributes = null;
            if ($required) {
                $attributes = array(
                    'disabled' => true
                );
            }
            $form->addRow($var['tag'], 'boolean', array(
               'label' =>  $var['tag'].(' (tag)'),
               'description' => $translator->translate('label.mailchimp.description.field'),
               'default' => $show,
               'attributes' => $attributes
            ));
        }

        $form = $form->build();

        if ($form->isSubmitted()) {
            if ($this->request->getBodyParameter('cancel')) {
                return false;
            }

            try {
                $form->validate();

                $data = $form->getData();
                $this->properties->setWidgetProperty('title.' . $this->locale, $data['title.' . $this->locale]);
                $this->properties->setWidgetProperty('apikey', $data['apikey']);
                $this->properties->setWidgetProperty('listid', $data['listid']);
                $this->properties->setWidgetProperty('finish.node', $data['finishNode']);

                $this->setTemplate($data[self::PROPERTY_TEMPLATE]);

                unset($data['title.' . $this->locale]);
                unset($data['apikey']);
                unset($data['listid']);
                unset($data[self::PROPERTY_TEMPLATE]);
                unset($data['finishNode']);
                unset($data['EMAIL']);

                foreach ($data as $key => $var) {
                    $value = false;
                    if (!empty($var)) {
                        $value = true;
                    }
                    $mailChimp->lists->mergeVarUpdate($listId, $key, array('public' => $value));
                }

                return true;

            } catch (ValidationException $exception) {
                $this->setValidationException($exception, $form);
            }
        }

        $this->setTemplateView(static::TEMPLATE_NAMESPACE . '/properties', array(
            'form' => $form->getView(),
        ));
    }

    /**
     * Gets the options for the styles
     * @return array Array with the name of the option as key and the
     * translation key as value
     */
    public function getWidgetStyleOptions() {
        return array(
            'container' => 'label.style.container',
            'title' => 'label.style.title',
        );
    }


    protected function getListVariables($mailChimp, $listId) {
        $list_vars = $mailChimp->lists->mergeVars(array($listId));
        $list_vars = $list_vars['data'][0]['merge_vars'];

        return $list_vars;
    }

}
