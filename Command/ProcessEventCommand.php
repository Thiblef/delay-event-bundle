<?php

namespace Itkg\DelayEventBundle\Command;

use Itkg\DelayEventBundle\DomainManager\EventManager;
use Itkg\DelayEventBundle\Exception\LockException;
use Itkg\DelayEventBundle\Handler\LockHandlerInterface;
use Itkg\DelayEventBundle\Model\Event;
use Itkg\DelayEventBundle\Processor\EventProcessor;
use Itkg\DelayEventBundle\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ProcessEventCommand
 */
class ProcessEventCommand extends ContainerAwareCommand
{
    /**
     * @var EventRepository
     */
    private $eventRepository;

    /**
     * @var EventProcessor
     */
    private $eventProcessor;

    /**
     * @var EventManager
     */
    private $eventManager;

    /**
     * @var LockHandlerInterface
     */
    private $lockHandler;

    /**
     * @param EventManager         $eventManager
     * @param EventRepository      $eventRepository
     * @param EventProcessor       $eventProcessor
     * @param LockHandlerInterface $lockHandler
     * @param null|string          $name
     */
    public function __construct(
        EventManager $eventManager,
        EventRepository $eventRepository,
        EventProcessor $eventProcessor,
        LockHandlerInterface $lockHandler,
        $name = null
    ) {
        $this->eventRepository = $eventRepository;
        $this->eventProcessor = $eventProcessor;
        $this->eventManager = $eventManager;
        $this->lockHandler = $lockHandler;

        parent::__construct($name);
    }

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName('itkg_delay_event:process')
            ->setDescription('Process async events');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @throws LockException
     * @throws \Exception
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($this->lockHandler->isLocked()) {
            throw new LockException('Command is locked by another processus');
        }

        $this->lockHandler->lock();

        $exception = null;
        try {
            while (true) {
                $event = $this->eventRepository->findFirstTodoEvent();
                if (!$event) {
                    break;
                }
                $event->setDelayed(false);
                $this->eventProcessor->process($event);
                $this->eventManager->delete($event);

            }
        } catch(\Exception $e) {
            $exception = $e;
        }
        $this->lockHandler->release();

        if ($exception) {
            throw $exception;
        }
    }
}
