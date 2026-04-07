<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use App\Repository\VueloRepository;

#[ORM\Entity(repositoryClass: VueloRepository::class)]
class Vuelo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $numero = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $horaSalidaProgramada = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $horaLlegadaProgramada = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $horaSalidaReal = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $horaLlegadaReal = null;

    #[ORM\ManyToOne(targetEntity: Estado::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Estado $estadoActual = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(string $numero): self
    {
        $this->numero = $numero;
        return $this;
    }

    public function getHoraSalidaProgramada(): ?\DateTimeInterface
    {
        return $this->horaSalidaProgramada;
    }

    public function setHoraSalidaProgramada(?\DateTimeInterface $horaSalidaProgramada): self
    {
        $this->horaSalidaProgramada = $horaSalidaProgramada;
        return $this;
    }

    public function getHoraLlegadaProgramada(): ?\DateTimeInterface
    {
        return $this->horaLlegadaProgramada;
    }

    public function setHoraLlegadaProgramada(?\DateTimeInterface $horaLlegadaProgramada): self
    {
        $this->horaLlegadaProgramada = $horaLlegadaProgramada;
        return $this;
    }

    public function getHoraSalidaReal(): ?\DateTimeInterface
    {
        return $this->horaSalidaReal;
    }

    public function setHoraSalidaReal(?\DateTimeInterface $horaSalidaReal): self
    {
        $this->horaSalidaReal = $horaSalidaReal;
        return $this;
    }

    public function getHoraLlegadaReal(): ?\DateTimeInterface
    {
        return $this->horaLlegadaReal;
    }

    public function setHoraLlegadaReal(?\DateTimeInterface $horaLlegadaReal): self
    {
        $this->horaLlegadaReal = $horaLlegadaReal;
        return $this;
    }

    public function getEstadoActual(): ?Estado
    {
        return $this->estadoActual;
    }

    public function setEstadoActual(?Estado $estadoActual): self
    {
        $this->estadoActual = $estadoActual;
        return $this;
    }
}
