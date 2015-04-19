<?php

namespace Sulu\Component\DocumentManager\Tests\Unit\Subscriber\Core;

use Sulu\Component\DocumentManager\DocumentRegistry;
use Sulu\Component\DocumentManager\Event\HydrateEvent;
use Sulu\Component\DocumentManager\Event\PersistEvent;
use Sulu\Component\DocumentManager\Event\RemoveEvent;
use Sulu\Component\DocumentManager\Subscriber\Core\RegistratorSubscriber;
use PHPCR\NodeInterface;

class RegistratorSubscriberTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->registry = $this->prophesize(DocumentRegistry::class);
        $this->subscriber = new RegistratorSubscriber(
            $this->registry->reveal()
        );

        $this->node = $this->prophesize(NodeInterface::class);
        $this->document = new \stdClass;
        $this->hydrateEvent = $this->prophesize(HydrateEvent::class);
        $this->persistEvent = $this->prophesize(PersistEvent::class);
        $this->removeEvent = $this->prophesize(RemoveEvent::class);
    }

    /**
     * It should set the document on hydrate if the document for the node to
     * be hydrated is already in the registry
     */
    public function testBeginHydrate()
    {
        $this->hydrateEvent->hasDocument()->willReturn(false);
        $this->hydrateEvent->getNode()->willReturn($this->node->reveal());
        $this->hydrateEvent->getLocale()->willReturn('fr');
        $this->registry->hasNode($this->node->reveal())->willReturn(true);
        $this->registry->getDocumentForNode($this->node->reveal())->willReturn($this->document);
        $this->registry->updateLocale($this->document, 'fr', 'fr')->shouldBeCalled();
        $this->hydrateEvent->setDocument($this->document)->shouldBeCalled();
        $this->subscriber->handleBeginHydrate($this->hydrateEvent->reveal());
    }

    /**
     * It should set the node to the event on persist if the node for the document
     * being persisted is already in the registry
     */
    public function testNodeFromRegistry()
    {
        $this->persistEvent->hasNode()->willReturn(false);
        $this->persistEvent->getDocument()->willReturn($this->document);
        $this->registry->hasDocument($this->document)->willReturn(true);
        $this->registry->getNodeForDocument($this->document)->willReturn($this->node->reveal());
        $this->persistEvent->setNode($this->node->reveal())->shouldBeCalled();
        $this->subscriber->handleNodeFromRegistry($this->persistEvent->reveal());
    }

    /**
     * It should return early if the document has already been set
     */
    public function testBeginHydrateAlreadySet()
    {
        $this->hydrateEvent->hasDocument()->willReturn(true);
        $this->subscriber->handleBeginHydrate($this->hydrateEvent->reveal());
    }

    /**
     * Is should return early if the node is not managed
     */
    public function testBeginHydrateNoNode()
    {
        $this->hydrateEvent->hasDocument()->willReturn(true);
        $this->hydrateEvent->getNode()->willReturn($this->node->reveal());
        $this->registry->hasNode($this->node->reveal())->willReturn(false);
        $this->subscriber->handleBeginHydrate($this->hydrateEvent->reveal());
    }

    /**
     * It should register documents on the HYDRATE event
     */
    public function testHandleRegisterHydrate()
    {
        $this->hydrateEvent->getDocument()->willReturn($this->document);
        $this->hydrateEvent->getNode()->willReturn($this->node->reveal());
        $this->hydrateEvent->getLocale()->willReturn('fr');
        $this->registry->hasDocument($this->document)->willReturn(false);
        $this->registry->registerDocument($this->document, $this->node->reveal(), 'fr')->shouldBeCalled();

        $this->subscriber->handleHydrate($this->hydrateEvent->reveal());
    }

    /**
     * It should not register documents on the HYDRATE event when there is already a document
     */
    public function testHandleRegisterHydrateAlreadyExisting()
    {
        $this->hydrateEvent->getDocument()->willReturn($this->document);
        $this->hydrateEvent->getNode()->willReturn($this->node->reveal());
        $this->hydrateEvent->getLocale()->willReturn('fr');

        $this->registry->hasDocument($this->document)->willReturn(true);
        $this->registry->registerDocument($this->document, $this->node->reveal(), 'fr')->shouldNotBeCalled();
        $this->registry->updateLocale($this->document, 'fr')->shouldBeCalled();

        $this->subscriber->handleHydrate($this->hydrateEvent->reveal());
    }

    /**
     * It should register documents on the PERSIST event
     */
    public function testHandleRegisterPersist()
    {
        $this->persistEvent->getDocument()->willReturn($this->document);
        $this->persistEvent->getNode()->willReturn($this->node->reveal());
        $this->persistEvent->getLocale()->willReturn('fr');
        $this->registry->hasDocument($this->document)->willReturn(false);
        $this->registry->registerDocument($this->document, $this->node->reveal(), 'fr')->shouldBeCalled();

        $this->subscriber->handlePersist($this->persistEvent->reveal());
    }

    /**
     * It should not register on PERSIST when there is already a document
     */
    public function testHandleRegisterPersistAlreadyExists()
    {
        $this->persistEvent->getDocument()->willReturn($this->document);
        $this->persistEvent->getNode()->willReturn($this->node->reveal());
        $this->persistEvent->getLocale()->willReturn('fr');

        $this->registry->registerDocument($this->document, $this->node->reveal(), 'fr')->shouldNotBeCalled();
        $this->registry->updateLocale($this->document, 'fr')->shouldBeCalled();
        $this->registry->hasDocument($this->document)->willReturn(true);

        $this->subscriber->handlePersist($this->persistEvent->reveal());
    }

    /**
     * It should deregister the document on the REMOVE event
     */
    public function testHandleRemove()
    {
        $this->removeEvent->getDocument()->willReturn($this->document);
        $this->registry->deregisterDocument($this->document)->shouldBeCalled();
        $this->subscriber->handleRemove($this->removeEvent->reveal());
    }
}
