<?php

namespace Sulu\Component\DocumentManager\Subscriber\Behavior;

use PHPCR\NodeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Sulu\Component\DocumentManager\DocumentRegistry;
use Symfony\Cmf\Bundle\CoreBundle\Slugifier\SlugifierInterface;
use Sulu\Component\DocumentManager\MetadataFactory;
use Sulu\Component\DocumentManager\Event\PersistEvent;
use Sulu\Component\DocumentManager\Behavior\AutoNameBehavior;
use Sulu\Component\DocumentManager\Exception\DocumentManagerException;
use Sulu\Component\DocumentManager\Events;
use PHPCR\Util\UUIDHelper;
use Sulu\Component\DocumentManager\Metadata;
use Sulu\Component\DocumentManager\NameResolver;

/**
 * Automatically assign a name to the document based on its title
 */
class AutoNameSubscriber implements EventSubscriberInterface
{
    private $documentRegistry;
    private $slugifier;
    private $metadataFactory;

    public function __construct(
        DocumentRegistry $documentRegistry,
        SlugifierInterface $slugifier,
        MetadataFactory $metadataFactory,
        NameResolver $resolver
    )
    {
        $this->documentRegistry = $documentRegistry;
        $this->slugifier = $slugifier;
        $this->metadataFactory = $metadataFactory;
        $this->resolver = $resolver;
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            Events::PERSIST => array('handlePersist', 480),
            Events::MOVE => array('handleMove', 480),
        );
    }

    /**
     * @param HydrateEvent $event
     */
    public function handlePersist(PersistEvent $event)
    {
        $document = $event->getDocument();

        if (!$document instanceof AutoNameBehavior) {
            return;
        }

        $title = $document->getTitle();
    
        if (!$title) {
            throw new DocumentManagerException(sprintf(
                'Document of class "%s" has no title (ooid: "%s")',
                get_class($document), spl_object_hash($document)
            ));
        }

        $name = $this->slugifier->slugify($title);
        $parentDocument = $document->getParent();

        if (null === $parentDocument) {
            throw new DocumentManagerException(sprintf(
                'Document with title "%s" has no parent, cannot automatically assing a name',
                $title
            ));
        }

        $parentNode = $this->documentRegistry->getNodeForDocument($parentDocument);
        $metadata = $this->metadataFactory->getMetadataForClass(get_class($document));

        $name = $this->resolver->resolveName($parentNode, $name);

        if (false === $event->hasNode()) {
            $node = $this->createNode($parentNode, $metadata, $name);
            $event->setNode($node);
            return;
        }

        $node = $event->getNode();
        if ($event->getLocale() === $this->documentRegistry->getDefaultLocale()) {
            $node->rename($name);
        }
    }

    /**
     * Create the node, add mixin and set the UUID
     *
     * TODO: Move this to separate subscriber, it should not be related to AutoName
     *
     * @param NodeInterface $parentNode
     * @param Metadata $metadata
     * @param mixed $name
     */
    private function createNode(NodeInterface $parentNode, Metadata $metadata, $name)
    {
        $node = $parentNode->addNode($name);

        // TODO: Migrate to using primary type
        $node->addMixin($metadata->getPhpcrType());
        $node->setProperty('jcr:uuid', UUIDHelper::generateUUID());

        return $node;
    }
}
