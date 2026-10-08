<?php

namespace App\Services;

class InstallerScriptService
{
    /**
     * Return the complete bash installer script for a blank Ubuntu VPS.
     */
    public function generateBashScript(): string
    {
        $scriptPath = base_path('scripts/install.sh');
        if (file_exists($scriptPath)) {
            return file_get_contents($scriptPath);
        }

        return "#!/usr/bin/env bash\necho 'Installer script not found.'\n";
    }
}
