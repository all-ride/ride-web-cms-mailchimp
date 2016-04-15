<?php

namespace ride\web\cms\controller\widget;

use DrewM\MailChimp\MailChimp;
use ride\library\cms\node\NodeModel;
use ride\library\validation\exception\ValidationException;


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
     * Name of the finish node property
     * @var string
     */
    const PROPERTY_FINISH_NODE = 'finish.node';

    /**
     * Name of the finish node property
     * @var string
     */
    const PROPERTY_ERROR_NODE = 'error.node';

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
        $listId = $this->properties->getLocalizedWidgetProperty($this->locale, 'listid');

        if (!$apiKey || !$listId) {
            return;
        }

        $parameters = $this->request->getQueryParameters();
        $translator = $this->getTranslator();
        $mailchimp = new MailChimp($apiKey);

        if (!$this->properties->getWidgetProperty('mailchimp')) {
            $fields = $this->getListVariables($mailchimp, $listId);
        } else {
            $fields = unserialize($this->properties->getWidgetProperty('mailchimp'));
        }
        $field_typeMapper = array(
            'email' => 'email',
            'text' => 'string',
            'date' => 'date',
        );

        $form = $this->createFormBuilder($parameters);

        $form->addRow('EMAIL', 'email', array(
            'label' => $translator->translate('label.mailchimp.email'),
            'validator' => array(
                'required' => array()
            )
        ));

        foreach ($fields as $field) {
            if ($field['public']) {
                if ($field['type'] == 'text' || $field['type'] == 'email') {
                    $attr = array(
                        'label' => $translator->translate('label.mailchimp.' . str_replace(' ', '_',strtolower($field['name']))),
                    );
                    if (isset($parameters[$field['tag']])) {

                        $attr['default_value'] = $parameters[$field['tag']];
                    }
                    if ($field['required']) {
                        $attr['validators'] = array(
                            'required' => array()
                        );
                    }
                    $field_type = $field_typeMapper[$field['type']];

                    $form->addRow($field['tag'], $field_type, $attr);

                }
            }
        }

        $form = $form->build();

        if ($form->isSubmitted()) {
            try {

                $form->validate();

                $data = $form->getData();
                $email = $data['EMAIL'];
                unset($data['EMAIL']);

                $variables = null;
                if($data) {
                    $variables = array();
                    foreach ($data as $key => $parameter) {
                        $variable = [];
                        $variable[$key] = $parameter;

                        $variables[$key] = $parameter;
                    }

                }

                if (!$variables) {
                    $response = $mailchimp->post("lists/$listId/members", [
                        'email_address' => $email,
                        'status' => 'subscribed',
                    ]);
                } else {
                    $response = $mailchimp->post("lists/$listId/members", [
                        'email_address' => $email,
                        'merge_fields' => $variables,
                        'status' => 'subscribed',
                    ]);

                }
                
                if ($response['status'] = 'subscribed') {
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
                } elseif ($response['status'] == 400) {
                    $errorMessage = $response['title'];
                    $error = $this->getErrorMessage($errorMessage);
                    $this->addError($error, array('error' => $errorMessage));

                    $error = $this->properties->getWidgetProperty('error.node');
                if ($error) {
                    $url = $this->getUrl('cms.front.' . $this->properties->getNode()->getRootNodeId() . '.' . $error . '.' . $this->locale);
                } else {

                    $filterUrl = str_replace('?' . $this->request->getQueryParametersAsString(), '', $this->request->getUrl());
                    $url = $filterUrl;
                }
                    $this->response->setRedirect($url);
                    return;
                }
            } catch (ValidationException $exception) {
                $this->setValidationException($exception, $form);
            }
        }

        $this->setTemplateView($this->getTemplate(static::TEMPLATE_NAMESPACE . '/default'), array(
            'title' => $this->properties->getLocalizedWidgetProperty($this->locale, 'title'),
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

        $title = $this->properties->getLocalizedWidgetProperty($this->locale, 'title');
        if ($title) {
            $preview .= '<strong>' . $translator->translate('label.title') .'</strong> ' . $title. '<br/>';
        }

        $apiKey = $this->properties->getWidgetProperty('apikey');
        if ($apiKey) {
            $preview .= '<strong>' . $translator->translate('label.key.api') .'</strong> ' . $apiKey . '<br/>';
        }

        $listId = $this->properties->getLocalizedWidgetProperty($this->locale, 'listid');
        if ($listId) {
            $preview .= '<strong>' . $translator->translate('label.id.list') . '</strong> ' . $listId . '<br/>';
        }

        if (!$apiKey || !$listId) {
            $preview = '<strong>' . $translator->translate('label.mailchimp.not.set') .  '</strong>';
        }

        return $preview;
    }

    /**
     * Action to setup the properties of this widget
     * @return null
     */
    public function propertiesAction(NodeModel $nodeModel) {

        $translator = $this->getTranslator();
        if ($this->properties->getWidgetProperty('apikey') && $this->properties->getLocalizedWidgetProperty($this->locale, 'listid') && !$this->properties->getWidgetProperty('mailchimp')) {
            $apiKey = $this->properties->getWidgetProperty('apikey');
            $listId = $this->properties->getLocalizedWidgetProperty($this->locale, 'listid');
            if (!$this->properties->getWidgetProperty('mailchimp')) {
                $mailChimp = new Mailchimp($apiKey);
                if ($listId) {
                    $list_vars = $mailChimp->get("/lists/$listId/merge-fields");
                    $list_vars = $list_vars['merge_fields'];
                } else {
                    $list_vars = array();
                }
                $this->properties->setWidgetProperty('mailchimp', serialize($list_vars));
            }
        }
        $data = array(
            'title' => $this->properties->getLocalizedWidgetProperty($this->locale, 'title'),
            'apikey' => $this->properties->getWidgetProperty('apikey'),
            'listid' => $this->properties->getLocalizedWidgetProperty($this->locale, 'listid'),
            'finishNode' => $this->properties->getWidgetProperty(self::PROPERTY_FINISH_NODE),
            'errorNode' => $this->properties->getWidgetProperty(self::PROPERTY_ERROR_NODE),
            static::PROPERTY_TEMPLATE => $this->getTemplate(static::TEMPLATE_NAMESPACE . '/default'),
        );
        $form = $this->createFormBuilder($data);
        $form->addRow('title', 'string', array(
            'label' => $translator->translate('label.title'),
        ));
        $form->addRow('apikey', 'string', array(
            'label' => $translator->translate('label.key.api'),
            'description' => $translator->translate('label.key.api.mailchimp.description'),
            'validators' => array(
                'required' => array()
            )
        ));
        $form->addRow('listid', 'string', array(
            'label' => $translator->translate('label.id.list'),
            'description' => $translator->translate('label.id.list.mailchimp.description'),
            'validators' => array(
                'required' => array()
            )
        ));
        $form->addRow('finishNode', 'select', array(
            'label' => $translator->translate('label.node.finish'),
            'description' => $translator->translate('label.node.finish.description'),
            'options' => $this->getNodeList($nodeModel),
        ));
        $form->addRow('errorNode', 'select', array(
            'label' => $translator->translate('label.node.error'),
            'description' => $translator->translate('label.node.error.description'),
            'options' => $this->getNodeList($nodeModel),
        ));
        $form->addRow(self::PROPERTY_TEMPLATE, 'select', array(
            'label' => $translator->translate('label.template'),
            'options' => $this->getAvailableTemplates(static::TEMPLATE_NAMESPACE),
            'validators' => array(
                'required' => array(),
            ),
        ));
        if ($this->properties->getWidgetProperty('mailchimp')) {
            $list_vars = unserialize($this->properties->getWidgetProperty('mailchimp'));
            foreach ($list_vars as $var) {

                $show = $var['public'];
                $required = $var['required'];
                $attributes = null;
                if ($required) {
                    $attributes = array(
                        'disabled' => true
                    );
                }
                $form->addRow($var['tag'], 'boolean', array(
                    'label' => $var['tag'] . (' (tag)'),
                    'description' => $translator->translate('label.mailchimp.description.field'),
                    'default' => $show,
                    'attributes' => $attributes
                ));
            }
        }

        $form = $form->build();

        if ($form->isSubmitted()) {
            if ($this->request->getBodyParameter('cancel')) {
                return false;
            }

            try {

                $form->validate();

                $data = $form->getData();


                $this->properties->setLocalizedWidgetProperty($this->locale, 'title', $data['title']);
                $this->properties->setWidgetProperty('apikey', $data['apikey']);
                $this->properties->setWidgetProperty('listid', $data['listid']);
                $this->properties->setWidgetProperty(self::PROPERTY_FINISH_NODE, $data['finishNode']);
                $this->properties->setWidgetProperty(self::PROPERTY_ERROR_NODE, $data['errorNode']);
                $this->setTemplate($data[static::PROPERTY_TEMPLATE]);


                unset($data['title']);
                unset($data['apikey']);
                unset($data['listid']);
                unset($data['localized']);
                unset($data[self::PROPERTY_TEMPLATE]);
                unset($data['finishNode']);
                unset($data['errorNode']);


                if ($this->properties->getWidgetProperty('mailchimp')) {
                    $list_vars = unserialize($this->properties->getWidgetProperty('mailchimp'));

                    foreach ($list_vars as $index => $var) {
                        foreach ($data as $key => $item) {
                            if ($var['tag'] == $key && $var['tag'] !== 'EMAIL') {
                                $var['public'] = ($item ? TRUE : FALSE);
                                $list_vars[$index] = $var;
                            }
                        }

                    }

                    $this->properties->setWidgetProperty('mailchimp', serialize($list_vars));
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
        $list_vars = $mailChimp->get("/lists/$listId/merge-fields");
        $list_vars = $list_vars['merge_fields'];

        return $list_vars;
    }

    protected function getErrorMessage($errorMessage) {
        $errorList = array(
            'Member Exists' => 'warning.mailchimp.email.exists'
        );

        if ($errorList[$errorMessage]) {
            return $errorList[$errorMessage];
        } else {
            return 'error.mailchimp.subscribe.general';
        }
    }

}
