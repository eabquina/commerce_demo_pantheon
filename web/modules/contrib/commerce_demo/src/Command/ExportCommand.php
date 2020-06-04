<?php

namespace Drupal\commerce_demo\Command;

// @codingStandardsIgnoreStart
use Drupal\commerce\EntityHelper;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityType;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Serialization\Yaml;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Drupal\Console\Core\Command\Shared\CommandTrait;
use Drupal\Console\Core\Style\DrupalStyle;
use Drupal\Console\Annotations\DrupalCommand;
use Drupal\commerce_demo\ContentExporter;
use Symfony\Component\Console\Question\ChoiceQuestion;
// @codingStandardsIgnoreEnd

/**
 * Class ExportCommand.
 *
 * @package Drupal\commerce_demo
 *
 * @DrupalCommand (
 *     extension="commerce_demo",
 *     extensionType="module"
 * )
 */
class ExportCommand extends Command {

  use CommandTrait;

  /**
   * The content exporter.
   *
   * @var \Drupal\commerce_demo\ContentExporter
   */
  protected $contentExporter;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Constructs a new ExportCommand object.
   *
   * @param \Drupal\commerce_demo\ContentExporter $content_exporter
   *   The content exporter.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   */
  public function __construct(ContentExporter $content_exporter, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    parent::__construct();

    $this->contentExporter = $content_exporter;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('commerce_demo:export')
      ->setDescription($this->trans('commands.commerce_demo.export.description'))
      ->addOption('directory', '', InputOption::VALUE_OPTIONAL, $this->trans('commands.commerce_demo.export.options.directory'))
      ->addArgument('entity_type', InputArgument::REQUIRED, $this->trans('commands.commerce_demo.export.arguments.entity_type'))
      ->addArgument('bundle', InputArgument::REQUIRED, $this->trans('commands.commerce_demo.export.arguments.bundle'));
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $entity_type_id = $input->getArgument('entity_type');
    $bundle = $input->getArgument('bundle');
    $directory = $input->getOption('directory');
    if ($directory && strpos($directory, -1, 1) != '/') {
      $directory .= '/';
    }
    // Add the bundle to the filename only if the entity type has one.
    $filename = $entity_type_id;
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    if ($entity_type->getKey('bundle')) {
      $filename .= '.' . $bundle;
    }
    $destination = $directory . $filename . '.yml';

    $export = $this->contentExporter->exportAll($entity_type_id, $bundle);
    file_put_contents($destination, Yaml::encode($export));

    $io = new DrupalStyle($input, $output);
    $io->writeln(sprintf($this->trans('commands.commerce_demo.export.messages.success'), $destination));
  }

  /**
   * {@inheritdoc}
   */
  protected function interact(InputInterface $input, OutputInterface $output) {
    $helper = $this->getHelper('question');
    $entity_types = $this->entityTypeManager->getDefinitions();
    $entity_types = array_filter($entity_types, function (EntityType $entity_type) {
      return $entity_type->entityClassImplements(ContentEntityInterface::class);
    });
    $entity_types = array_map(function (EntityType $entity_type) {
      return $entity_type->getLabel();
    }, $entity_types);

    // --entity_type argument.
    $entity_type_id = $input->getArgument('entity_type');
    if (!$entity_type_id) {
      $question = new ChoiceQuestion(
        $this->trans('commands.commerce_demo.export.questions.entity_type'),
        $entity_types
      );
      $entity_type_id = $helper->ask($input, $output, $question);
    }
    $input->setArgument('entity_type', $entity_type_id);

    // --bundle argument.
    $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
    $bundles = array_map(function ($bundle) {
      return $bundle['label'];
    }, $bundles);
    if (count($bundles) === 1) {
      $bundle_keys = array_keys($bundles);
      $bundle = reset($bundle_keys);
    }
    else {
      $bundle = $input->getArgument('bundle');
      if (!$bundle) {
        $question = new ChoiceQuestion(
          $this->trans('commands.commerce_demo.export.questions.bundle'),
          $bundles
        );
        $bundle = $helper->ask($input, $output, $question);
      }
    }
    $input->setArgument('bundle', $bundle);
  }

}
