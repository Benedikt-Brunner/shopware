<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Doctrine\DBAL\Connection;
use Psr\EventDispatcher\StoppableEventInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Flow\Exception\ExecuteSequenceException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\FlowLogEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal not intended for decoration or replacement
 */
#[Package('services-settings')]
class FlowDispatcher implements EventDispatcherInterface
{
    private ContainerInterface $container;
    public array $bufferedEvents = [];
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $logger,
        private readonly FlowFactory $flowFactory,
        private readonly Connection $connection,
    ) {
    }

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }


    /**
     * @template TEvent of object
     *
     * @param TEvent $event
     *
     * @return TEvent
     */
    public function dispatch(object $event, ?string $eventName = null): object
    {
        $event = $this->dispatcher->dispatch($event, $eventName);

        if (!$event instanceof FlowEventAware) {
            return $event;
        }

        $flowLogEvent = new FlowLogEvent(FlowLogEvent::NAME, $event);
        $this->dispatcher->dispatch($flowLogEvent, $flowLogEvent->getName());

        if (($event instanceof StoppableEventInterface && $event->isPropagationStopped())
            || $event->getContext()->hasState(Context::SKIP_TRIGGER_FLOW)
        ) {
            return $event;
        }

        $this->bufferedEvents[] = $event;
        return $event;

        $storableFlow = $this->flowFactory->create($event);
        $this->callFlowExecutor($storableFlow);

        return $event;
    }

    /**
     * @param callable $listener can not use native type declaration @see https://github.com/symfony/symfony/issues/42283
     */
    public function addListener(string $eventName, $listener, int $priority = 0): void // @phpstan-ignore-line
    {
        $this->dispatcher->addListener($eventName, $listener, $priority);
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->dispatcher->addSubscriber($subscriber);
    }

    public function removeListener(string $eventName, callable $listener): void
    {
        $this->dispatcher->removeListener($eventName, $listener);
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->dispatcher->removeSubscriber($subscriber);
    }

    /**
     * @return array<array-key, array<array-key, callable(object): void>|callable(object): void>
     */
    public function getListeners(?string $eventName = null): array
    {
        return $this->dispatcher->getListeners($eventName);
    }

    public function getListenerPriority(string $eventName, callable $listener): ?int
    {
        return $this->dispatcher->getListenerPriority($eventName, $listener);
    }

    public function hasListeners(?string $eventName = null): bool
    {
        return $this->dispatcher->hasListeners($eventName);
    }

    public function callFlowExecutor(StorableFlow $event): void
    {
        $flows = $this->getFlows($event->getName());

        if (empty($flows)) {
            return;
        }

        $flowExecutor = $this->container->get(FlowExecutor::class);

        if ($flowExecutor === null) {
            throw new ServiceNotFoundException(FlowExecutor::class);
        }

        foreach ($flows as $flow) {
            $executionPayload = [
                'id' => Uuid::randomBytes(),
                'flow_id' => hex2bin($flow['id']),
                'trigger_context' => json_encode($event->stored()),
            ];

            try {
                $payload = $flow['payload'];
                $flowExecutor->execute($payload, $event);

                $executionPayload['successful'] = 1;
                $executionPayload['error_message'] = null;
            } catch (ExecuteSequenceException $e) {
                $this->logger->warning(
                    "Could not execute flow with error message:\n"
                    . 'Flow name: ' . $flow['name'] . "\n"
                    . 'Flow id: ' . $flow['id'] . "\n"
                    . 'Sequence id: ' . $e->getSequenceId() . "\n"
                    . $e->getMessage() . "\n"
                    . 'Error Code: ' . $e->getCode() . "\n",
                    ['exception' => $e]
                );
                $executionPayload['successful'] = 0;
                $executionPayload['error_message'] = $e->getMessage();
            } catch (\Throwable $e) {
                $this->logger->error(
                    "Could not execute flow with error message:\n"
                    . 'Flow name: ' . $flow['name'] . "\n"
                    . 'Flow id: ' . $flow['id'] . "\n"
                    . $e->getMessage() . "\n"
                    . 'Error Code: ' . $e->getCode() . "\n",
                    ['exception' => $e]
                );
                $executionPayload['successful'] = 0;
                $executionPayload['error_message'] = $e->getMessage();
            } finally {
                if ($this->isInNestedTransaction()) {
                    $this->handleTransactionLevelMismatch();
                    $executionPayload['successful'] = 0;
                    $executionPayload['error_message'] ??= 'Transaction level was not 0 after flow execution';
                }
                $this->connection->insert('flow_execution', $executionPayload);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getFlows(string $eventName): array
    {
        $flowLoader = $this->container->get(FlowLoader::class);

        if ($flowLoader === null) {
            throw new ServiceNotFoundException(FlowExecutor::class);
        }

        $flows = $flowLoader->load();

        $result = [];
        if (\array_key_exists($eventName, $flows)) {
            $result = $flows[$eventName];
        }

        return $result;
    }

    private function isInNestedTransaction(): bool
    {
        return $this->connection->getTransactionNestingLevel() !== 0;
    }

    private function handleTransactionLevelMismatch()
    {
        while ($this->isInNestedTransaction()) {
            $this->connection->rollBack();
        }
    }
}
