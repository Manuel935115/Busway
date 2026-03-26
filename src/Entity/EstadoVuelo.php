<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use App\Repository\EstadoVueloRepository;

#[ORM\Entity(repositoryClass: EstadoVueloRepository::class)]
class EstadoVuelo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Vuelo::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Vuelo $vuelo = null;

    #[ORM\ManyToOne(targetEntity: Estado::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Estado $estado = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fechaHora = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $horaSalida = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $horaLlegada = null;

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

    public function getVuelo(): ?Vuelo
    {
        return $this->vuelo;
    }

    public function setVuelo(?Vuelo $vuelo): self
    {
        $this->vuelo = $vuelo;
        return $this;
    }

    public function getEstado(): ?Estado
    {
        return $this->estado;
    }

    public function setEstado(?Estado $estado): self
    {
        $this->estado = $estado;
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

    public function getHoraSalida(): ?\DateTimeInterface
    {
        return $this->horaSalida;
    }

    public function setHoraSalida(?\DateTimeInterface $horaSalida): self
    {
        $this->horaSalida = $horaSalida;
        return $this;
    }

    public function getHoraLlegada(): ?\DateTimeInterface
    {
        return $this->horaLlegada;
    }

    public function setHoraLlegada(?\DateTimeInterface $horaLlegada): self
    {
        $this->horaLlegada = $horaLlegada;
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
