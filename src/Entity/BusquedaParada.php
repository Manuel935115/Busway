<?php

namespace App\Entity;

use App\Repository\BusquedaParadaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BusquedaParadaRepository::class)]
class BusquedaParada
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $stopId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stopName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaHora = null;

    public function __construct()
    {
        $this->fechaHora = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getStopId(): ?string { return $this->stopId; }
    public function setStopId(string $stopId): self { $this->stopId = $stopId; return $this; }

    public function getStopName(): ?string { return $this->stopName; }
    public function setStopName(?string $stopName): self { $this->stopName = $stopName; return $this; }

    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): self { $this->address = $address; return $this; }

    public function getFechaHora(): ?\DateTimeInterface { return $this->fechaHora; }
    public function setFechaHora(\DateTimeInterface $fechaHora): self { $this->fechaHora = $fechaHora; return $this; }
}
