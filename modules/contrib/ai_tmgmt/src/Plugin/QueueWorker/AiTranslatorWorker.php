<?php

declare(strict_types=1);

namespace Drupal\ai_tmgmt\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai_tmgmt\Plugin\tmgmt\Translator\AiTranslator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The queue worker for AI translations.
 *
 * @QueueWorker(
 *   id = "ai_translator_worker",
 *   title = @Translation("AI TMGMT translate queue worker"),
 *   cron = {"time" = 120}
 * )
 */
class AiTranslatorWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  /**
   * The logger channel interface.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface|null
   */
  protected ?LoggerChannelInterface $logger;

  /**
   * Constructs a \Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): AiTranslatorWorker {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory'),
    );
  }

  /**
   * Job item translation handler via Cron.
   *
   * @param array $data
   *   An associative array containing the following, passed from AiTranslator.
   *   [
   *     'job' => $job,
   *     'job_item' => $job_item,
   *     'translation' => $translation,
   *     'key' => $key,
   *     'keys_sequence' => $keys_sequence,
   *     'chunk' => $chunk,
   *   ].
   */
  public function processItem($data): void {
    try {
      $context = [];

      // Run the regular batch operations here.
      AiTranslator::batchRequestTranslation(
        $data['job'],
        $data['job_item'],
        $data['translation'],
        $data['key'],
        $data['keys_sequence'],
        $data['chunk'],
        NULL,
        $context,
      );
      $context['results']['job_item'] = $data['job_item'];
      AiTranslator::batchFinished(TRUE, $context['results'], []);
    }
    catch (\Exception $exception) {
      $this->logger()->error($this->t('Unable to translate job item: @id, the following exception was thrown: @message', [
        '@id' => $data['job_item']->id(),
        '@message' => $exception->getMessage(),
      ]));
    }
  }

  /**
   * Getter for the logger.
   *
   * @return \Drupal\Core\Logger\LoggerChannelInterface
   *   The registered logger for this channel.
   */
  public function logger(): LoggerChannelInterface {
    if (empty($this->logger)) {
      $this->logger = $this->loggerFactory->get('ai_tmgmt');
    }
    return $this->logger;
  }

}
