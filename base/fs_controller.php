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

require_once 'base/fs_db.php';
require_once 'base/fs_button.php';
require_once 'model/fs_user.php';
require_once 'model/fs_page.php';
require_once 'model/fs_access.php';
require_once 'model/empresa.php';

class fs_controller
{
   protected $db;
   private $uptime;
   public $error_msg;
   public $message;
   public $user;
   public $page;
   public $ppage;
   private $admin_page;
   public $default_page;
   protected $menu;
   public $template;
   public $css_file;
   public $custom_search;
   public $query;
   public $buttons;
   private $empresa_name;

   public function __construct($name='', $title='home', $folder='', $admin=FALSE, $shmenu=TRUE)
   {
      $tiempo = explode(' ', microtime());
      $this->uptime = $tiempo[1] + $tiempo[0];
      $this->admin_page = $admin;
      $this->template = 'login';
      $this->error_msg = FALSE;
      $this->message = FALSE;
      $this->db = new fs_db();
      
      $this->set_css_file();
      
      if( $this->db->connect() )
      {
         $this->user = new fs_user();
         /// recuperamos el mensaje de error de fs_user()
         $this->new_error_msg( $this->user->error_msg );
         
         $this->page = new fs_page( array('name'=>$name,
                                          'title'=>$title,
                                          'folder'=>$folder,
                                          'version'=>$this->version(),
                                          'show_on_menu'=>$shmenu) );
         /// recuperamos el mensaje de error de fs_page()
         $this->new_error_msg( $this->page->error_msg );
         $this->ppage = FALSE;
         
         $this->empresa_name = $this->get_empresa_name();
         
         if( isset($_GET['logout']) )
            $this->log_out();
         else if($this->log_in() AND $this->user_have_access())
         {
            if($name == '')
               $this->template = 'index';
            else
            {
               if( isset($_GET['default_page']) )
                  $this->set_default_page();
               else if( !isset($_COOKIE['default_page']) )
                  $this->default_page = FALSE;
               else if($_COOKIE['default_page'] == $this->page->name)
                  $this->default_page = TRUE;
               
               $this->buttons = array();
               
               $this->custom_search = FALSE;
               if( isset($_POST['query']) )
                  $this->query = $_POST['query'];
               else if( isset($_GET['query']) )
                  $this->query = $_GET['query'];
               else
                  $this->query = '';
               
               $this->template = $name;
               $this->process();
            }
         }
         else if($this->user->logged_on )
            $this->template = 'access_denied';
      }
      else
         $this->new_error_msg('¡Imposible conectar con la base de datos!');
   }
   
   public function close()
   {
      $this->db->close();
   }
   
   public function new_error_msg($msg)
   {
      if( !$this->error_msg )
         $this->error_msg = $msg;
      else
         $this->error_msg .= "<br/>" . $msg;
   }
   
   public function new_message($msg)
   {
      if( !$this->message )
         $this->message = $msg;
      else
         $this->message .= '<br/>' . $msg;
   }

   public function log_in()
   {
      if(isset($_POST['user']) AND isset($_POST['password']))
      {
         $user = $this->user->get($_POST['user']);
         if($user)
         {
            if($user->password == sha1($_POST['password']))
            {
               $user->new_logkey();
               if( $user->save() )
               {
                  setcookie('user', $user->nick, time()+FS_COOKIES_EXPIRE);
                  setcookie('logkey', $user->log_key, time()+FS_COOKIES_EXPIRE);
                  $this->user = $user;
                  $this->load_menu();
               }
            }
            else
               $this->new_error_msg('Contraseña incorrecta!');
         }
         else
            $this->new_error_msg('El usuario no existe!');
      }
      else if(isset($_COOKIE['user']) AND isset($_COOKIE['logkey']))
      {
         $user = $this->user->get($_COOKIE['user']);
         if($user)
         {
            if($user->log_key == $_COOKIE['logkey'])
            {
               $user->logged_on = TRUE;
               $this->user = $user;
               $this->load_menu();
            }
            else
            {
               $this->new_message('¡Cookie no válida!');
               $this->log_out();
            }
         }
         else
         {
            $this->new_message('¡El usuario no existe!');
            $this->log_out();
         }
      }
      return $this->user->logged_on;
   }
   
   public function log_out()
   {
      setcookie('logkey', '', time()-FS_COOKIES_EXPIRE);
      setcookie('empresa', '', time()-FS_COOKIES_EXPIRE);
   }
   
   public function duration()
   {
      $tiempo = explode(" ", microtime());
      $tiempo = $tiempo[1] + $tiempo[0];
      return (number_format($tiempo - $this->uptime, 3) . ' segundos');
   }
   
   public function selects()
   {
      return $this->db->get_selects();
   }
   
   public function transactions()
   {
      return $this->db->get_transactions();
   }
   
   public function get_empresa_name()
   {
      $name = '';
      if( isset($this->empresa_name) )
         $name = $this->empresa_name;
      else if( isset($_COOKIE['empresa']) )
         $name = $_COOKIE['empresa'];
      else
      {
         $e = new empresa();
         setcookie('empresa', $e->nombre, time()+FS_COOKIES_EXPIRE);
         $name = $e->nombre;
      }
      return $name;
   }
   
   protected function load_menu()
   {
      $this->menu = array();
      $menu = $this->page->all();
      if(count($menu) > 0)
      {
         /// actualizamos los datos de la página
         foreach($menu as $m)
         {
            if($m->name == $this->page->name AND $m != $this->page)
            {
               $this->page->save();
               break;
            }
         }
         
         if( $this->user->admin )
            $this->menu = $menu;
         else
         {
            foreach($menu as $m)
            {
               /// decidimos si se lo mostramos al usuario o no
               foreach($this->user->get_accesses() as $a)
               {
                  if($m->name == $a->fs_page)
                  {
                     $this->menu[] = $m;
                     break;
                  }
               }
            }
         }
      }
   }
   
   public function folders()
   {
      $folders = array();
      foreach($this->menu as $m)
      {
         if($m->folder!='' AND $m->show_on_menu AND !in_array($m->folder, $folders) )
            $folders[] = $m->folder;
      }
      return $folders;
   }
   
   public function pages($f='')
   {
      $pages = array();
      foreach($this->menu as $p)
      {
         if($f == $p->folder AND $p->show_on_menu AND !in_array($p, $pages) )
            $pages[] = $p;
      }
      return $pages;
   }
   
   protected function process()
   {
      
   }
   
   public function version()
   {
      return '0.9.7';
   }
   
   public function select_default_page()
   {
      if( $this->user->logged_on )
      {
         if( isset($_COOKIE['default_page']) )
         {
            $page = $this->page->get($_COOKIE['default_page']);
            if($page)
               Header('location: index.php?page=' . $_COOKIE['default_page']);
            else if(count($this->menu) > 0)
               Header('location: index.php?page=' . $this->menu[0]->name);
            else
               Header('location: index.php?page=admin_pages');
         }
         else if(count($this->menu) > 0)
            Header('location: index.php?page=' . $this->menu[0]->name);
         else
            Header('location: index.php?page=admin_pages');
      }
   }
   
   public function set_default_page()
   {
      if($_GET['default_page'] == 'TRUE')
      {
         setcookie('default_page', $this->page->name, time()+315360000);
         $this->default_page = TRUE;
      }
      else
      {
         setcookie('default_page', '', time()-FS_COOKIES_EXPIRE);
         $this->default_page = FALSE;
      }
   }
   
   public function set_css_file()
   {
      if( isset($_GET['css_file']) )
      {
         $this->css_file = $_GET['css_file'];
         setcookie('css_file', $_GET['css_file'], time()+315360000);
      }
      else if( isset($_COOKIE['css_file']) )
         $this->css_file = $_COOKIE['css_file'];
      else
         $this->css_file = 'base.css';
   }

   public function is_admin_page()
   {
      return $this->admin_page;
   }
   
   public function user_have_access()
   {
      if( $this->user->admin )
         return TRUE;
      else if( !$this->admin_page )
      {
         $a = new fs_access( array('fs_user'=>$this->user->nick,
                                   'fs_page'=>$this->page->name) );
         return $a->exists();
      }
      else
         return FALSE;;
   }
   
   public function url()
   {
      return $this->page->url();
   }
   
   public function db_history_enabled()
   {
      return FS_DB_HISTORY;
   }
   
   public function get_db_history()
   {
      return $this->db->get_history();
   }
}

?>