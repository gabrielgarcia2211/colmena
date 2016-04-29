<?php

namespace Colmena\Http\Controllers;

use Illuminate\Http\Request;
use Colmena\Cusuario;
use Colmena\Http\Requests;
use Colmena\Http\Controllers\Controller;
use Colmena\Ctarea;
use Colmena\CBitaTarea;
use Auth;
use DB;
use Illuminate\Pagination\LengthAwarePaginator;

//Borrar lo siguiente cuando deje de usarse;
use Faker\Factory as Faker;

class CtareasController extends Controller
{
/**
En los metodos por ruta se antepone el tipo de peticion http. Ej getNombreMetodo
postNombreMetodo etc. Y eso en las rutas se manejará como en minuscula de ma-
nera que queden así, x ej: colmena.cr/modulo/listar ... que hace referen-
cia al metodo getListar del controlador de ese modulo.
*/
    public function __construct(){
        $this->middleware("auth");
    }
    public function getListar($idUsu = false){
        //Si no se está pasando ningun usuario
        if($idUsu === false){
            //por defecto se listan las tareas propias
            $tareas = Ctarea::where('idUsu', Auth::user()->idUsu)->get();
            if((Auth::user()->tieneAccion('tareas.listar')))
                //Y si tiene la accion de listar tareas, se listan todas
                $tareas=Ctarea::all();
        }
        //Si por SÍ se está pasando un usuario
        else{
            //Si son sus propias tareas
            if($idUsu == Auth::user()->idUsu){
                $tareas = Ctarea::where('idUsu', Auth::user()->idUsu)->get();
            }
            //si NO son sus propias tareas
            else{
                //Si no puede listar, se redirecciona
                if(!(Auth::user()->tieneAccion('tareas.listar')))
                    return redirect('errores/acceso-negado');
                //si SÍ puede listar, se listan las tareas de el usuario requerido
                //$user = Cusuario::find($idUsu);
                if($user)
                    $tareas = Ctarea::where('idUsu', $idUsu)->get();
                else
                    return view('tareas.listar')->with('estado', 'error')->with('tareas', null);
            }
        }
        //Esto acá hay que mejorarlo, hay que ver cual es el error de las relaciones del ORM, reparar y usar
        foreach($tareas as $Otarea)
            $Otarea->usuarioResponsable = Cusuario::findOrFail($Otarea->idUsu);
        return view("tareas.listar")->with('tareas',$tareas);
    }
    public function getVer(Request $request, $idTar){
        $Otarea = Ctarea::find($idTar);
        if(!Auth::user()->tieneAccion('tareas.listar') && $Otarea->idUsu != Auth::user()->idUsu)
            return redirect('errores/acceso-negado');
        if($Otarea->idUsu == Auth::user()->idUsu && !$Otarea->visto){
            $Otarea->visto = true;
            $Otarea->save();
        }
        $bitacoras = CBitaTarea::all();
        $Otarea->usuarioResponsable = Cusuario::findOrFail($Otarea->idUsu);
        if($Otarea)
            $bitacora = CBitaTarea::where('idTar', '=', $Otarea->idTar)->paginate(5);
            return view('tareas/ver')->with('Otarea', $Otarea)->with('bitacora', $bitacora);
        return redirect('tareas/listar');
    }
    public function getBitacora(Request $request){
        $Otarea = Ctarea::find($request->idTar);
        if(!(Auth::user()->tieneAccion('tareas.listar'))
                && $Otarea->idUsu != Auth::user()->idUsu
                && !(Auth::user()->tieneRolPorNombre('Jefe de Departamento')))
            return redirect('errores/acceso-negado');
        $arrEstados = ['Asignada','Revision','Cumplida','Cancelada','Diferida','Retrasada'];
        return view('tareas.bitacora')->with('Otarea', $Otarea)->with('arrEstados', $arrEstados);
    }
    public function postBitacora(Request $request){
        $Otarea = Ctarea::findOrFail($request->idTar);
        if(!(Auth::user()->tieneAccion('tareas.listar')) && $Otarea->idUsu != Auth::user()->idUsu)
            return redirect('errores/acceso-negado');

        $Otarea->estTar = $request->input('status');
        $Otarea->save();

        $Obitacora = New CBitaTarea;
        $Obitacora->idTar = $Otarea->idTar;
        $Obitacora->detalle = $request->input("incidencia");
        $Obitacora->save();

        $arrEstados = ['Asignada','Revision','Cumplida','Cancelada','Diferida','Retrasada'];
        return redirect('tareas/listar')->with('estado', 'realizado');
        //return view("tareas.bitacora")->with('Otarea', $Otarea)
            //->with('estado', 'realizado')
            //->with('arrEstados', $arrEstados);
        //return $Otarea;
    }
    public function getRegistrar(){
        if(!(\Auth::user()->tieneAccion('tareas.registrar')))
            return redirect('errores/acceso-negado');
    	$Ousuarios = Cusuario::all();
        return view("tareas.registrar")->with('Ousuarios',$Ousuarios);
    }

    public function postRegistrar(Request $request){
        if(!(\Auth::user()->tieneAccion('tareas.registrar')))
            return redirect('errores/acceso-negado');
    	$Ousuarios=Cusuario::all();
    	//$oTarea=Ctarea::find($request->input("title"));
        $Ousuario=Cusuario::findOrFail($request->input("responsable"));

        $Otarea = New Ctarea;
        $Otarea->titulo = $request->input("title");
        $Otarea->fecEst = $request->input("deliverdate");
        $Otarea->detalle = $request->input("details");
        $Otarea->prioridad = $request->input("priority");
        $Otarea->complejidad = $request->input("complexity");
        $Otarea->estTar = 'Asignada';
        $Otarea->tipTar = $request->input("tipoTarea");
        $Otarea->idUsu = $Ousuario->idUsu;
        $Otarea->save();
        //Debe implementarse lo de abajo en un for cuando se implemente una tarea de envío multiples usuarios
        CTarea::enviarEmailTareaAsignada($Otarea);
        return redirect("tareas/registrar")->with(['Ousuarios'=>$Ousuarios, 'estado' => 'realizado']);
    }
    public function getModificar(Request $request, $idTarea = -1){
        if(!(\Auth::user()->tieneAccion('tareas.modificar')))
            return redirect('errores/acceso-negado');
        if($idTarea != -1){
            $Otarea=Ctarea::find($idTarea);
            if($Otarea){
                $Ousuarios = Cusuario::all();
                return view("tareas.modificar")->with('Otarea', $Otarea)->with('Ousuarios', $Ousuarios);
            }
        }
        return redirect('/tareas/listar')->with('estado', 'no-seleccionado');
    }
    public function postModificar(Request $request){
        //dd($request);
        $Otarea=Ctarea::findOrFail($request->get('idTar'));
        if( !is_null($request->input("title")))
            $Otarea->titulo = $request->get("title");

        if( !is_null($request->input("deliverdate")))
            $Otarea->fecEst=$request->input("deliverdate");

        if( !is_null($request->input("details")))
            $Otarea->detalle=$request->input("details");

        if( !is_null($request->input("priority")))
            $Otarea->prioridad=$request->input("priority");

        if( !is_null($request->input("complexity")))
            $Otarea->complejidad=$request->input("complexity");

        if( !is_null($request->input("tipoTarea")))
            $Otarea->tipTar=$request->input("tipoTarea");

        if( !is_null($request->input("responsable")))
            $Otarea->idUsu=$request->input("responsable");
        $Otarea->save();

        $tareas = Ctarea::all();
        foreach($tareas as $ItemOtarea){
            $ItemOtarea->usuarioResponsable = Cusuario::findOrFail($ItemOtarea->idUsu);
        }
        return redirect("/tareas/listar")
                ->with('tareas', $tareas)
                ->with('estado', 'realizado');
    }
    public function getEliminar(){
        return redirect('/tareas/listar')->with('estado', 'no-seleccionado');
    }
    public function postEliminar(Request $request){
        if(!(\Auth::user()->tieneAccion('tareas.eliminar')))
            return redirect('errores/acceso-negado');
        $Oeliminada = Ctarea::find($request->get('idTar'));
        $Oeliminada->delete();
        $tareas=Ctarea::all();
        foreach($tareas as $tarea){
            $tarea->usuarioResponsable = Cusuario::findOrFail($tarea->idUsu);
            //La anterior es la  linea que tienes que copiar y adaptar
        }
        return view("tareas.listar")->with('tareas',$tareas);
    }

}
