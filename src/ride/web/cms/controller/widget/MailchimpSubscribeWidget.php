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
    const TEMPLATE = 'cms/widget/mailchimp/subscribe';

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

        $translator = $this->getTranslator();

        $form = $this->createFormBuilder();
        $form->addRow('email', 'email', array(
            'label' => "",
            'attributes' => array(
                'placeholder' => $translator->translate('label.email.your'),
            ),
            'validators' => array(
                'required' => array()
            )
        ));
        $form = $form->build();

        if ($form->isSubmitted()) {
            try {
                $form->validate();

                $data = $form->getData();
                $email = array('email' => $data['email']);

                $mailchimp = new Mailchimp($apiKey);

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
                            $mailchimp->lists->subscribe($listId, $email, null, 'html', false, true, false, $code == 232);

                            $this->addSuccess('success.mailchimp.subscribe');

                            break;
                        default:
                            $this->addWarning('error.mailchimp.subscribe.general', array('error' => $response['errors']['0']['message'])));

                            break;
                    }
                } else {
                    $this->addError('error.mailchimp.subscribe.unknown');
                }
            } catch (ValidationException $exception) {
                $this->setValidationException($exception, $form);
            }
        }

        $this->setTemplateView(self::TEMPLATE, array(
            'title' => $this->properties->getWidgetProperty('title.' . $this->locale),
            'form' => $form->getView(),
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
    public function propertiesAction() {
        $translator = $this->getTranslator();

        $data = array(
            'title' => $this->properties->getWidgetProperty('title.' . $this->locale),
            'apikey' => $this->properties->getWidgetProperty('apikey'),
            'listid' => $this->properties->getWidgetProperty('listid'),
        );

        $form = $this->createFormBuilder($data);
        $form->addRow('title', 'string', array(
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
        $form = $form->build();

        if ($form->isSubmitted()) {
            if ($this->request->getBodyParameter('cancel')) {
                return false;
            }

            try {
                $form->validate();

                $data = $form->getData();

                $this->properties->setWidgetProperty('title.' . $this->locale, $data['title']);
                $this->properties->setWidgetProperty('apikey', $data['apikey']);
                $this->properties->setWidgetProperty('listid', $data['listid']);

                return true;
            } catch (ValidationException $exception) {
                $this->setValidationException($exception, $form);
            }
        }

        $this->setTemplateView('cms/widget/mailchimp/properties', array(
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
            'container' => 'label.widget.style.container',
            'title' => 'label.widget.style.title',
        );
    }

}
