<?php

namespace App\Auth;

/**
 * Socialite driver for VATSIM Scandinavia's Handover, which offers the same OAuth2 endpoints and
 * user details as VATSIM Connect, so only the server differs.
 *
 * Handover turns away people banned in the vACC or suspended from VATSIM on its own site, so
 * they never come back here.
 */
class HandoverProvider extends VatsimConnectProvider
{
    protected string $baseUrl = 'https://handover.vatsim-scandinavia.org';
}
