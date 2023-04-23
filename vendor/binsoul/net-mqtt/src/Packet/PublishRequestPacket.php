<?php

declare(strict_types=1);

namespace BinSoul\Net\Mqtt\Packet;

use BinSoul\Net\Mqtt\Exception\MalformedPacketException;
use BinSoul\Net\Mqtt\Packet;
use BinSoul\Net\Mqtt\PacketStream;
use InvalidArgumentException;

/**
 * Represents the PUBLISH packet.
 */
class PublishRequestPacket extends BasePacket
{
    use IdentifiablePacket;

    /** @var string */
    private $topic;
    /** @var string */
    private $payload;

    protected static $packetType = Packet::TYPE_PUBLISH;

    public function read(PacketStream $stream): void
    {
        parent::read($stream);
        $this->assertRemainingPacketLength();

        $originalPosition = $stream->getPosition();
        $this->topic = $stream->readString();
        $this->identifier = null;
        if ($this->getQosLevel() > 0) {
            $this->identifier = $stream->readWord();
        }

        $payloadLength = $this->remainingPacketLength - ($stream->getPosition() - $originalPosition);
        $this->payload = $stream->read($payloadLength);

        $this->assertValidQosLevel($this->getQosLevel());
        $this->assertValidString($this->topic);
    }

    public function write(PacketStream $stream): void
    {
        $data = new PacketStream();

        $data->writeString($this->topic);
        if ($this->getQosLevel() > 0) {
            $data->writeWord($this->generateIdentifier());
        }

        $data->write($this->payload);

        $this->remainingPacketLength = $data->length();

        parent::write($stream);
        $stream->write($data->getData());
    }

    /**
     * Returns the topic.
     */
    public function getTopic(): string
    {
        return $this->topic;
    }

    /**
     * Sets the topic.
     *
     * @throws InvalidArgumentException
     */
    public function setTopic(string $value): void
    {
        if ($value === '') {
            throw new InvalidArgumentException('The topic must not be empty.');
        }

        try {
            $this->assertValidString($value);
        } catch (MalformedPacketException $e) {
            throw new InvalidArgumentException($e->getMessage());
        }

        $this->topic = $value;
    }

    /**
     * Returns the payload.
     */
    public function getPayload(): string
    {
        return $this->payload;
    }

    /**
     * Sets the payload.
     */
    public function setPayload(string $value): void
    {
        $this->payload = $value;
    }

    /**
     * Indicates if the packet is a duplicate.
     */
    public function isDuplicate(): bool
    {
        return ($this->packetFlags & 8) === 8;
    }

    /**
     * Marks the packet as duplicate.
     */
    public function setDuplicate(bool $value): void
    {
        if ($value) {
            $this->packetFlags |= 8;
        } else {
            $this->packetFlags &= ~8;
        }
    }

    /**
     * Indicates if the packet is retained.
     */
    public function isRetained(): bool
    {
        return ($this->packetFlags & 1) === 1;
    }

    /**
     * Marks the packet as retained.
     */
    public function setRetained(bool $value): void
    {
        if ($value) {
            $this->packetFlags |= 1;
        } else {
            $this->packetFlags &= ~1;
        }
    }

    /**
     * Returns the quality of service level.
     */
    public function getQosLevel(): int
    {
        return ($this->packetFlags & 6) >> 1;
    }

    /**
     * Sets the quality of service level.
     *
     * @throws InvalidArgumentException
     */
    public function setQosLevel(int $value): void
    {
        try {
            $this->assertValidQosLevel($value);
        } catch (MalformedPacketException $e) {
            throw new InvalidArgumentException($e->getMessage());
        }

        $this->packetFlags |= ($value & 3) << 1;
    }
}
