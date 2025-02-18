<?php
/**
 * Spotler Transactional plugin for Craft CMS
 *
 * @link      https://perfectwebteam.com
 * @copyright Copyright (c) 2025 Perfect Web Team
 */

namespace perfectwebteam\spotlertransactional\mail;

use Craft;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\helpers\App;
use craft\mail\transportadapters\BaseTransportAdapter;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Spotler Transactional Adaptor
 *
 * @author    Perfect Web Team
 * @package   Spotler Transactional
 * @since     1.0.0
 *
 * @property-read mixed $settingsHtml
 */
class SpotlerTransactionalAdapter extends BaseTransportAdapter
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return 'Spotler Transactional';
    }

    /**
     * @var string The CLIENT ID that should be used
     */
    public string $clientId = '';

    /**
     * @var string The CLIENT SECRET that should be used
     */
    public string $clientSecret = '';

    /**
     * @var string The subaccount that should be used
     */
    public string $subaccount = '';

    /**
     * @var string The template that should be used
     */
    public string $template = '';

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'clientId' => Craft::t('spotler-transactional', 'Client ID'),
            'clientSecret' => Craft::t('spotler-transactional', 'Client SECRET'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['parser'] = [
            'class' => EnvAttributeParserBehavior::class,
            'attributes' => [
                'clientId',
                'clientSecret',
                'subaccount',
                'template'
            ],
        ];
        return $behaviors;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = [['clientId'], 'required'];
        $rules[] = [['clientSecret'], 'required'];
        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('spotler-transactional/settings', [
            'adapter' => $this,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function defineTransport(): array|AbstractTransport
    {
        $transport = new SpotlerTransactionalTransport(App::parseEnv($this->clientId), App::parseEnv($this->clientSecret));

        if ($this->template) {
            $transport->setTemplate(App::parseEnv($this->template));
        }

        if ($this->subaccount) {
            $transport->setSubaccount(App::parseEnv($this->subaccount));
        }

        return $transport;
    }
}