<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'servicios')]
class Servicio
{
    #[ORM\Id]
    #[ORM\Column(length: 12)]
    private ?string $codigo = null;

    #[ORM\Column(length: 45)]
    private ?string $origen = null;

    #[ORM\Column(length: 45)]
    private ?string $destino = null;

    #[ORM\Column(length: 45)]
    private ?string $pasajeros = null;

    #[ORM\Column(length: 100)]
    private ?string $fecha = null;

    #[ORM\Column(name: 'vuelo_tren', length: 100)]
    private ?string $vueloTren = null;

    #[ORM\Column(length: 300)]
    private ?string $coord = null;

    public function getCodigo(): ?string { return $this->codigo; }
    public function setCodigo(string $v): self { $this->codigo = $v; return $this; }

    public function getOrigen(): ?string { return $this->origen; }
    public function setOrigen(string $v): self { $this->origen = $v; return $this; }

    public function getDestino(): ?string { return $this->destino; }
    public function setDestino(string $v): self { $this->destino = $v; return $this; }

    public function getPasajeros(): ?string { return $this->pasajeros; }
    public function setPasajeros(string $v): self { $this->pasajeros = $v; return $this; }

    public function getFecha(): ?string { return $this->fecha; }
    public function setFecha(string $v): self { $this->fecha = $v; return $this; }

    public function getVueloTren(): ?string { return $this->vueloTren; }
    public function setVueloTren(string $v): self { $this->vueloTren = $v; return $this; }

    public function getCoord(): ?string { return $this->coord; }
    public function setCoord(string $v): self { $this->coord = $v; return $this; }
}
