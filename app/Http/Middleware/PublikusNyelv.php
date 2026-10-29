<?php

namespace App\Http\Middleware;

use App\Support\Nyelv;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A publikus útvonal nyelve (a routes/web.php adja paraméterként: hu = gyökér, ro = /ro/ előtag). */
class PublikusNyelv
{
    public function handle(Request $request, Closure $next, string $nyelv): Response
    {
        app()->setLocale(in_array($nyelv, Nyelv::NYELVEK, true) ? $nyelv : 'hu');

        return $next($request);
    }
}
