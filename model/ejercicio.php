<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2012  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_once 'base/fs_model.php';

class ejercicio extends fs_model
{
   public $idasientocierre;
   public $idasientopyg;
   public $idasientoapertura;
   public $plancontable;
   public $longsubcuenta;
   public $estado;
   public $fechafin;
   public $fechainicio;
   public $nombre;
   public $codejercicio;
   
   private static $default_ejercicio;

   public function __construct($e = FALSE)
   {
      parent::__construct('ejercicios');
      if($e)
      {
         $this->idasientocierre = $this->intval($e['idasientocierre']);
         $this->idasientopyg = $this->intval($e['idasientopyg']);
         $this->idasientoapertura = $this->intval($e['idasientoapertura']);
         $this->plancontable = $e['plancontable'];
         $this->longsubcuenta = $this->intval($e['longsubcuenta']);
         $this->estado = $e['estado'];
         $this->fechafin = Date('d-m-Y', strtotime($e['fechafin']));
         $this->fechainicio = Date('d-m-Y', strtotime($e['fechainicio']));
         $this->nombre = $e['nombre'];
         $this->codejercicio = $e['codejercicio'];
      }
      else
      {
         $this->idasientocierre = NULL;
         $this->idasientopyg = NULL;
         $this->idasientoapertura = NULL;
         $this->plancontable = '08';
         $this->longsubcuenta = NULL;
         $this->estado = 'ABIERTO';
         $this->fechafin = Date('31-12-Y');
         $this->fechainicio = Date('01-01-Y');
         $this->nombre = '';
         $this->codejercicio = NULL;
      }
      
   }
   
   public function get_new_codigo()
   {
      $cod = $this->db->select("SELECT MAX(codejercicio::integer) as cod FROM ".$this->table_name.";");
      if($cod)
         return sprintf('%04s', (1 + intval($cod[0]['cod'])));
      else
         return '0001';
   }
   
   public function url()
   {
      return 'index.php?page=contabilidad_ejercicios#'.$this->codejercicio;
   }
   
   public function is_default()
   {
      if( isset(self::$default_ejercicio) )
         return (self::$default_ejercicio == $this->codejercicio);
      else if( !isset($_COOKIE['default_ejercicio']) )
         return FALSE;
      else if($_COOKIE['default_ejercicio'] == $this->codejercicio)
         return TRUE;
      else
         return FALSE;
   }
   
   public function set_default()
   {
      setcookie('default_ejercicio', $this->codejercicio, time()+FS_COOKIES_EXPIRE);
      self::$default_ejercicio = $this->codejercicio;
   }
   
   protected function install()
   {
      return "INSERT INTO ".$this->table_name." (codejercicio,nombre,fechainicio,fechafin,estado,longsubcuenta,plancontable,
            idasientoapertura,idasientopyg,idasientocierre) VALUES ('0001','".Date('Y')."',
            '".Date('01-01-Y')."','".Date('31-12-Y')."','ABIERTO',NULL,'08',NULL,NULL,NULL);";
   }
   
   public function exists()
   {
      if( is_null($this->codejercicio) )
         return FALSE;
      else
         return $this->db->select("SELECT * FROM ".$this->table_name." WHERE codejercicio = '".$this->codejercicio."';");
   }
   
   public function save()
   {
      if( $this->exists() )
      {
         $sql = "UPDATE ".$this->table_name." SET nombre = ".$this->var2str($this->nombre).",
            fechainicio = ".$this->var2str($this->fechainicio).", fechafin = ".$this->var2str($this->fechafin).",
            estado = ".$this->var2str($this->estado).", logsubcuenta = ".$this->var2str($this->longsubcuenta).",
            plancontable = ".$this->var2str($this->plancontable).", idasientoapertura = ".$this->var2str($this->idasientoapertura).",
            idasientopyg = ".$this->var2str($this->idasientopyg).", idasientocierre = ".$this->var2str($this->idasientocierre)."
            WHERE codejercicio = '".$this->codejercicio."';";
      }
      else
      {
         $sql = "INSERT INTO ".$this->table_name." (codejercicio,nombre,fechainicio,fechafin,estado,longsubcuenta,plancontable,
            idasientoapertura,idasientopyg,idasientocierre) VALUES (".$this->var2str($this->codejercicio).",".$this->var2str($this->nombre).",
            ".$this->var2str($this->fechainicio).",".$this->var2str($this->fechafin).",".$this->var2str($this->estado).",
            ".$this->var2str($this->longsubcuenta).",".$this->var2str($this->plancontable).",
            ".$this->var2str($this->idasientoapertura).",".$this->var2str($this->idasientopyg).",
            ".$this->var2str($this->idasientocierre).");";
      }
      return $this->db->exec($sql);
   }
   
   public function delete()
   {
      return $this->db->exec("DELETE FROM ".$this->table_name." WHERE codejercicio = '".$this->codejercicio."';");
   }
   
   public function all()
   {
      $listae = array();
      $ejercicios = $this->db->select("SELECT * FROM ".$this->table_name." ORDER BY codejercicio DESC;");
      if($ejercicios)
      {
         foreach($ejercicios as $e)
            $listae[] = new ejercicio($e);
      }
      return $listae;
   }
   
   public function get($cod)
   {
      $ejercicio = $this->db->select("SELECT * FROM ".$this->table_name." WHERE codejercicio = '".$cod."';");
      if($ejercicio)
         return new ejercicio($ejercicio[0]);
      else
         return FALSE;
   }
}

?>