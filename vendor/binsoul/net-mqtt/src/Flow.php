<?php

declare(strict_types=1);

namespace BinSoul\Net\Mqtt;

use Exception;

/**
 * Represents a sequence of packages exchanged between clients and brokers.
 */
interface Flow
{
    /**
     * Returns the unique code.
     */
    public function getCode(): string;

    /**
     * Starts the flow.
     *
     * @return Packet|null First packet of the flow
     *
     * @throws Exception
     */
    public function start(): ?Packet;

    /**
     * Indicates if the flow can handle the given packet.
     *
     * @param Packet $packet Packet to accept
     */
    public function accept(Packet $packet): bool;

    /**
     * Continues the flow.
     *
     * @param Packet $packet Packet to respond
     *
     * @return Packet|null Next packet of the flow
     */
    public function next(Packet $packet): ?Packet;

    /**
     * Indicates if the flow is finished.
     */
    public function isFinished(): bool;

    /**
     * Indicates if the flow finished successfully.
     */
    public function isSuccess(): bool;

    /**
     * Returns the result of the flow if it finished successfully.
     *
     * @return mixed
     */
    public function getResult();

    /**
     * Returns an error message if the flow didn't finish successfully.
     */
    public function getErrorMessage(): string;
}
