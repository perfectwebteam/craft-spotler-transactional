<?php
/**
 * Spotler Transactional plugin for Craft CMS
 *
 * @link      https://perfectwebteam.com
 * @copyright Copyright (c) 2025 Perfect Web Team
 */

namespace perfectwebteam\spotlertransactional;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\MailerHelper;
use perfectwebteam\spotlertransactional\mail\SpotlerTransactionalAdapter;
use perfectwebteam\spotlertransactional\models\Settings;
use yii\base\Event;

/**
 * Spotler Transactional Plugin
 *
 * @author    Perfect Web Team
 * @package   Spotler Transactional
 * @since     1.0.0
 * @method Settings getSettings()
 */
class SpotlerTransactional extends Plugin
{
    /**
     * @var SpotlerTransactional
     */
    public static SpotlerTransactional $plugin;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

	    // $eventName = defined(sprintf('%s::EVENT_REGISTER_MAILER_TRANSPORT_TYPES', MailerHelper::class))
		//     ? MailerHelper::EVENT_REGISTER_MAILER_TRANSPORT_TYPES // Craft 4
		//     : MailerHelper::EVENT_REGISTER_MAILER_TRANSPORTS; // Craft 5+

        // Event::on(
        //     MailerHelper::class,
	    //     $eventName,
        //     static function(RegisterComponentTypesEvent $event) {
        //         $event->types[] = SpotlerTransactionalAdapter::class;
        //     }
        // );
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }
}