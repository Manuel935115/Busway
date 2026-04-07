<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use App\Repository\EstadoTrenRepository;

#[ORM\Entity(repositoryClass: EstadoTrenRepository::class)]
class EstadoTren
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Tren::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Tren $tren = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaHora = null;

    #[ORM\Column(nullable: true)]
    private ?int $retraso = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $origen = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $destino = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $rawData = null;

    public function __construct()
    {
        $this->fechaHora = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTren(): ?Tren
    {
        return $this->tren;
    }

    public function setTren(Tren $tren): self
    {
        $this->tren = $tren;
        return $this;
    }

    public function getFechaHora(): ?\DateTimeInterface
    {
        return $this->fechaHora;
    }

    public function setFechaHora(\DateTimeInterface $fechaHora): self
    {
        $this->fechaHora = $fechaHora;
        return $this;
    }

    public function getRetraso(): ?int
    {
        return $this->retraso;
    }

    public function setRetraso(?int $retraso): self
    {
        $this->retraso = $retraso;
        return $this;
    }

    public function getOrigen(): ?string
    {
        return $this->origen;
    }

    public function setOrigen(?string $origen): self
    {
        $this->origen = $origen;
        return $this;
    }

    public function getDestino(): ?string
    {
        return $this->destino;
    }

    public function setDestino(?string $destino): self
    {
        $this->destino = $destino;
        return $this;
    }

    public function getRawData(): ?array
    {
        return $this->rawData;
    }

    public function setRawData(?array $rawData): self
    {
        $this->rawData = $rawData;
        return $this;
    }
}
