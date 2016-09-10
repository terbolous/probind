<?php
/**
 * ProBIND v3 - Professional DNS management made easy.
 *
 * Copyright (c) 2016 by Paco Orozco <paco@pacoorozco.info>
 *
 * This file is part of some open source application.
 *
 * Licensed under GNU General Public License 3.0.
 * Some rights reserved. See LICENSE, AUTHORS.
 *
 * @author      Paco Orozco <paco@pacoorozco.info>
 * @copyright   2016 Paco Orozco
 * @license     GPL-3.0 <http://spdx.org/licenses/GPL-3.0>
 * @link        https://github.com/pacoorozco/probind
 */

namespace App\Http\Controllers;

use App\Server;
use App\Zone;
use Artisan;
use Illuminate\Http\Request;

class ToolsController extends Controller
{

    /**
     * Show the summary page before push updates to servers.
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function viewUpdates()
    {
        $servers = Server::withPushCapability()
            ->orderBy('hostname')
            ->get();

        $zonesToUpdate = Zone::withPendingChanges()
            ->orderBy('domain')
            ->get();

        $zonesToDelete = Zone::onlyTrashed()
            ->orderBy('domain')
            ->get();

        return view('tools.push')
            ->with('servers', $servers)
            ->with('zonesToUpdate', $zonesToUpdate)
            ->with('zonesToDelete', $zonesToDelete);
    }

    /**
     * Push updates to servers.
     *
     * @return \Illuminate\Http\RedirectResponse
     * @codeCoverageIgnore
     */
    public function pushUpdates()
    {
        // create config files

        // create zone files and push to servers
        \Artisan::call('probind:push');

        // mark zones delete

        return redirect()->route('home')
            ->with('success', trans('tools/messages.push_updates_success'));
    }

    /**
     * Show the summary page before bulk update.
     *
     * @return \Illuminate\View\View
     */
    public function showBulkUpdate()
    {
        return view('tools.bulk_update');
    }

    /**
     * Push updates to servers.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function doBulkUpdate()
    {
        $zones = Zone::all();
        foreach ($zones as $zone) {
            $zone->setPendingChanges();
        }

        return redirect()->route('home')
            ->with('success', trans('tools/messages.bulk_update_success'));
    }

    /**
     * Show the form to import a zone from a RFC 1033 file.
     *
     * @return \Illuminate\View\View
     */
    public function importZone()
    {
        return view('tools.import_zone');
    }

    /**
     * Call Artisan 'probind:import' command with supplied data.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function importZonePost(Request $request)
    {
        $this->validate($request, [
            'domain'    => 'required|string',
            'zonefile'  => 'required|file|max:2048',
            'overwrite' => 'sometimes|boolean'
        ]);

        // Validator fails

        /*

        try {
            $fname = md5(rand()) . '.xlsx';
            $full_path = Config::get('filesystems.disks.local.root');
            $excel_file->move( $full_path, $fname );
            $flag_table = Flag::firstOrNew(['file_name'=>$fname]);
            $flag_table->imported = 0; //file was not imported
            $flag_table->save();
        }catch(\Exception $e){
            return Redirect::to(route('home'))
                ->withErrors($e->getMessage()); //don't use this in production ok ?
        }

        */

        Artisan::call('probind:import', [
            'zone'     => $request->input('domain'),
            'zonefile' => $request->file('zonefile')->path(),
            '--force'  => $request->has('overwrite'),
        ]);

        return redirect()->route('home')
            ->with('success',
                trans('tools/messages.import_zone_success', ['zone' => $request->input('domain')]));
    }
}
