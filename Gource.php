<?php

#region Gource

class Gource {

    private static $initialized = false;
    public static $name = 'gource';
    public static $installed = false;
    public static $page = 8;
    public static $rank = 100;
    public static $enabledShow = true;
    private static $langTemplate = 'Gource';
    public static $onEvents = array(
        'createGourceData' => array(
            'name' => 'createGourceData',
            'event' => array('actionCreateGourceData'),
            'procedure' => 'installCreateGourceData',
            'enabledInstall' => true
        ),
        'listGourceData' => array(
            'name' => 'listGourceData',
            'event' => array('actionListGourceData'),
            'procedure' => 'installListGourceData',
            'enabledInstall' => true
        ),
        'executeGource' => array(
            'name' => 'executeGource',
            'event' => array('actionExecuteGource'),
            'procedure' => 'installExecuteGource',
            'enabledInstall' => true
        ),
        'executeGourceOnly' => array(
            'name' => 'executeGourceOnly',
            'event' => array('actionExecuteGourceOnly'),
            'procedure' => 'installExecuteGourceOnly',
            'enabledInstall' => true
        ),
        'listGourceResult' => array(
            'name' => 'listGourceResult',
            'event' => array('actionListGourceResult'),
            'procedure' => 'installListGourceResult',
            'enabledInstall' => true
        ),
        'convertGource' => array(
            'name' => 'convertGource',
            'event' => array('actionConvertGource'),
            'procedure' => 'installConvertGource',
            'enabledInstall' => true
        )
    );

    public static function getDefaults($data) {
        $res = array(
            'path' => array('data[GOURCE][path]', '/var/www/gource'),
            'selectedData' => array('data[GOURCE][selectedData]', NULL),
            'selectedResult' => array('data[GOURCE][selectedResult]', NULL),
            'beginTimestamp' => array('data[GOURCE][beginTimestamp]', '1970-12-08')
        );
        $res['repos'] = array();
        $pluginFiles = Paketverwaltung::getPackageDefinitions($data);
        foreach ($pluginFiles as $plug) {
            $input = Paketverwaltung::gibPaketInhalt($data, $plug);
            if ($input !== null) {
                $entries = array();
                Paketverwaltung::gibPaketEintraegeNachTyp($input, 'git', $entries);
                foreach ($entries as $git) {
                    $path = $git['params']['path'];
                    $name = md5($path);
                    $res['repos'][$name] = array('data[GOURCE][REPO][' . $name . ']', NULL);
                }
            }
        }
        return $res;
    }

    public static function checkExecutability($data) {
        Installation::log(array('text' => Installation::Get('main', 'functionBegin')));
        $res = array(
                ['name' => 'gource', 'exec' => 'gource --help', 'desc' => 'gource --help'],
                ['name' => 'ffmpeg', 'exec' => 'ffmpeg -version', 'desc' => 'ffmpeg -version']);
        Installation::log(array('text' => Installation::Get('main', 'functionEnd')));
        return $res;
    }

    /**
     * initialisiert das Segment
     * @param type $console
     * @param string[][] $data die Serverdaten
     * @param bool $fail wenn ein Fehler auftritt, dann auf true setzen
     * @param string $errno im Fehlerfall kann hier eine Fehlernummer angegeben werden
     * @param string $error ein Fehlertext für den Fehlerfall
     */
    public static function init($console, &$data, &$fail, &$errno, &$error) {
        Installation::log(array('text' => Installation::Get('main', 'functionBegin')));
        Language::loadLanguageFile('de', self::$langTemplate, 'json', dirname(__FILE__) . '/');
        Installation::log(array('text' => Installation::Get('main', 'languageInstantiated')));

        $def = self::getDefaults($data);

        $text = '';
        $text .= Design::erstelleVersteckteEingabezeile($console, $data['GOURCE']['path'], 'data[GOURCE][path]', $def['path'][1], true);
        if (isset($data['GOURCE']['selectedData']) && !file_exists($data['GOURCE']['selectedData'])) {
            $data['GOURCE']['selectedData'] = NULL;
        }
        $text .= Design::erstelleVersteckteEingabezeile($console, $data['GOURCE']['selectedData'], 'data[GOURCE][selectedData]', $def['selectedData'][1], true);

        if (isset($data['GOURCE']['selectedResult']) && !file_exists($data['GOURCE']['selectedResult'])) {
            $data['GOURCE']['selectedResult'] = NULL;
        }
        $text .= Design::erstelleVersteckteEingabezeile($console, $data['GOURCE']['selectedResult'], 'data[GOURCE][selectedResult]', $def['selectedResult'][1], true);

        $text .= Design::erstelleVersteckteEingabezeile($console, $data['GOURCE']['beginTimestamp'], 'data[GOURCE][beginTimestamp]', $def['beginTimestamp'][1], true);

        
        foreach ($def['repos'] as $defName => $defVar) {
            $text .= Design::erstelleVersteckteEingabezeile($console, $data['GOURCE']['REPO'][$defName], $defVar[0], $defVar[1], true);
        }

        echo $text;

        self::$initialized = true;
        Installation::log(array('text' => Installation::Get('main', 'functionEnd')));
    }

    public static function show($console, $result, $data) {
        if (!Einstellungen::$accessAllowed) {
            return;
        }
        
        if (!Paketverwaltung::isPackageSelected($data, 'GOURCE')){
            return;
        }

        Installation::log(array('text' => Installation::Get('main', 'functionBegin')));
        $text = '';
        $text .= Design::erstelleBeschreibung($console, Installation::Get('main', 'description', self::$langTemplate));

        $text .= Design::erstelleZeile($console, Installation::Get('createGourceData', 'path', self::$langTemplate), 'e', Design::erstelleEingabezeile($console, $data['GOURCE']['path'], 'data[GOURCE][path]', $data['GOURCE']['path'], true), 'v');
        $text .= Design::erstelleZeile($console, Installation::Get('createGourceData', 'beginTimestamp', self::$langTemplate), 'e', Design::erstelleDatumsfeld($console, $data['GOURCE']['beginTimestamp'], 'data[GOURCE][beginTimestamp]', $data['GOURCE']['beginTimestamp'], true), 'v');
        
        if (self::$onEvents['createGourceData']['enabledInstall']) {
            $mainPath = $data['PL']['localPath'];
            $mainPath = str_replace(array("\\", "/"), array(DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR), $mainPath);
            $pluginFiles = Paketverwaltung::getSelectedPackageDefinitions($data);
            $gitResults = array();
            foreach ($pluginFiles as $plug) {
                $input = Paketverwaltung::gibPaketInhalt($data, $plug);
                if ($input !== null) {
                    $entries = array();
                    Paketverwaltung::gibPaketEintraegeNachTyp($input, 'git', $entries);
                    $gitResults = array_merge($entries, $gitResults);
                }
            }

            usort($gitResults, function ($a, $b) {
                $displayNameA = (isset($a['params']['name']) ? $a['params']['name'] : '');
                $displayNameB = (isset($b['params']['name']) ? $b['params']['name'] : '');
                return strcmp($displayNameA, $displayNameB);
            });

            foreach ($gitResults as $git) {
                $path = $git['params']['path'];
                $displayName = (isset($git['params']['name']) ? $git['params']['name'] : '???');
                $name = md5($path);
                $text .= Design::erstelleZeile($console, $displayName, 'e', Design::erstelleAuswahl($console, $data['GOURCE']['REPO'][$name], 'data[GOURCE][REPO][' . $name . ']', $name, null, true), 'h');
            }

            $text .= Design::erstelleZeile($console, Installation::Get('createGourceData', 'createDesc', self::$langTemplate), 'e', Design::erstelleSubmitButton(self::$onEvents['createGourceData']['event'][0], Installation::Get('createGourceData', 'create', self::$langTemplate)), 'h');
        }

        $createBackup = false;
        if (isset($result[self::$onEvents['createGourceData']['name']])) {
            $content = $result[self::$onEvents['createGourceData']['name']]['content'];
            if (!isset($content['outputFile'])) {
                $content['outputFile'] = '???';
            }
            if (!isset($content['outputSize'])) {
                $content['outputSize'] = '???';
            }

            $createBackup = true;
            if (!empty($content['output'])) {
                $text .= Design::erstelleZeile($console, Installation::Get('createGourceData', 'databaseMessage', self::$langTemplate), 'e', $content['databaseOutput'], 'v error_light break');
            }

            $text .= Design::erstelleZeile($console, Installation::Get('createGourceData', 'filePath', self::$langTemplate), 'e', $content['outputFile'], 'v');
            $text .= Design::erstelleZeile($console, Installation::Get('createGourceData', 'fileSize', self::$langTemplate), 'e', Design::formatBytes($content['outputSize']), 'v');
        }

        if (self::$onEvents['listGourceData']['enabledInstall']) {
            $text .= Design::erstelleZeile($console, Installation::Get('listGourceData', 'listDesc', self::$langTemplate), 'e', Design::erstelleSubmitButton(self::$onEvents['listGourceData']['event'][0], Installation::Get('listGourceData', 'list', self::$langTemplate)), 'h');
        }

        if (isset($result[self::$onEvents['listGourceData']['name']])) {
            $content = $result[self::$onEvents['listGourceData']['name']]['content'];
            if (!isset($content['gourceData'])) {
                $content['gourceData'] = array();
            }

            $content['gourceData'] = array_reverse($content['gourceData']);
            foreach ($content['gourceData'] as $key => $file) {
                if ($key == 0) {
                    $text .= Design::erstelleZeile($console, '', '', '', '');
                }

                $text .= Design::erstelleZeile($console, Installation::Get('listGourceData', 'filePath', self::$langTemplate), 'e', $file['file'], 'v');
                if (isset($file['size'])) {
                    $text .= Design::erstelleZeile($console, Installation::Get('listGourceData', 'fileSize', self::$langTemplate), 'e', Design::formatBytes($file['size']), 'v');
                }

                if (self::$onEvents['executeGource']['enabledInstall']) {
                    $text .= Design::erstelleZeile($console, Installation::Get('listGourceData', 'select', self::$langTemplate), 'e', Design::erstelleGruppenAuswahl($console, $data['GOURCE']['selectedData'], 'data[GOURCE][selectedData]', $file['file'], NULL, true), 'h');
                }

                if ($key != count($content['gourceData']) - 1) {
                    $text .= Design::erstelleZeile($console, '', '', '', '');
                }
            }

            if (empty($content['gourceData'])) {
                $text .= Design::erstelleZeile($console, '', 'e', Installation::Get('listGourceData', 'noData', self::$langTemplate), 'v_c');
            } else {
                if (self::$onEvents['executeGource']['enabledInstall']) {
                    $text .= Design::erstelleZeile($console, Installation::Get('executeGource', 'executeDesc', self::$langTemplate), 'e', Design::erstelleSubmitButton(self::$onEvents['executeGource']['event'][0], Installation::Get('executeGource', 'execute', self::$langTemplate)), 'h');
                }
                if (self::$onEvents['executeGourceOnly']['enabledInstall']) {
                    $text .= Design::erstelleZeile($console, Installation::Get('executeGourceOnly', 'executeDesc', self::$langTemplate), 'e', Design::erstelleSubmitButton(self::$onEvents['executeGourceOnly']['event'][0], Installation::Get('executeGourceOnly', 'execute', self::$langTemplate)), 'h');
                }
            }
        }

        if (self::$onEvents['listGourceResult']['enabledInstall']) {
            $text .= Design::erstelleZeile($console, Installation::Get('listGourceResult', 'listDesc', self::$langTemplate), 'e', Design::erstelleSubmitButton(self::$onEvents['listGourceResult']['event'][0], Installation::Get('listGourceResult', 'list', self::$langTemplate)), 'h');
        }

        if (isset($result[self::$onEvents['listGourceResult']['name']])) {
            $content = $result[self::$onEvents['listGourceResult']['name']]['content'];
            if (!isset($content['gourceResult'])) {
                $content['gourceResult'] = array();
            }

            $content['gourceResult'] = array_reverse($content['gourceResult']);
            foreach ($content['gourceResult'] as $key => $file) {
                if ($key == 0) {
                    $text .= Design::erstelleZeile($console, '', '', '', '');
                }

                $text .= Design::erstelleZeile($console, Installation::Get('listGourceResult', 'filePath', self::$langTemplate), 'e', $file['file'], 'v');
                if (isset($file['size'])) {
                    $text .= Design::erstelleZeile($console, Installation::Get('listGourceResult', 'fileSize', self::$langTemplate), 'e', Design::formatBytes($file['size']), 'v');
                }

                if (self::$onEvents['convertGource']['enabledInstall']) {
                    $text .= Design::erstelleZeile($console, Installation::Get('listGourceResult', 'select', self::$langTemplate), 'e', Design::erstelleGruppenAuswahl($console, $data['GOURCE']['selectedResult'], 'data[GOURCE][selectedResult]', $file['file'], NULL, true), 'h');
                }

                if ($key != count($content['gourceResult']) - 1) {
                    $text .= Design::erstelleZeile($console, '', '', '', '');
                }
            }

            if (empty($content['gourceResult'])) {
                $text .= Design::erstelleZeile($console, '', 'e', Installation::Get('listGourceResult', 'noData', self::$langTemplate), 'v_c');
            } else {
                if (self::$onEvents['convertGource']['enabledInstall']) {
                    $text .= Design::erstelleZeile($console, Installation::Get('convertGource', 'executeDesc', self::$langTemplate), 'e', Design::erstelleSubmitButton(self::$onEvents['convertGource']['event'][0], Installation::Get('convertGource', 'execute', self::$langTemplate)), 'h');
                }
            }
        }

        echo Design::erstelleBlock($console, Installation::Get('main', 'title', self::$langTemplate), $text);

        Installation::log(array('text' => Installation::Get('main', 'functionEnd')));
        return null;
    }

    private static function filter($mode, $name, $excludeList,
            $excludeContainsList) {
        //if ($mode === 'M') return true;
        foreach ($excludeList as $ex) {
            if (substr($name, 0, strlen($ex)) === $ex) {
                return false;
            }
        }

        foreach ($excludeContainsList as $ex) {
            if (strpos($name, $ex) !== false) {
                return false;
            }
        }
        return true;
    }

    public static function installCreateGourceData($data, &$fail, &$errno,
            &$error) {
        Installation::log(array('text' => Installation::Get('main', 'functionBegin')));

        $res = array();
        $location = $data['GOURCE']['path'];
        Einstellungen::generatepath($location);

        $mainPath = $data['PL']['localPath'];
        $mainPath = str_replace(array("\\", "/"), array(DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR), $mainPath);
        $pluginFiles = Paketverwaltung::getSelectedPackageDefinitions($data);
        $gitResults = array();
        foreach ($pluginFiles as $plug) {
            $input = Paketverwaltung::gibPaketInhalt($data, $plug);
            if ($input !== null) {
                $entries = array();
                Paketverwaltung::gibPaketEintraegeNachTyp($input, 'git', $entries);
                $gitResults = array_merge($entries, $gitResults);
            }
        }

        $repositories = array();
        foreach ($gitResults as $git) {
            $path = $git['params']['path'];
            $displayName = $git['params']['name'];
            $name = md5($path);
            if (isset($data['GOURCE']['REPO'][$name]) && $data['GOURCE']['REPO'][$name] === $name) {
                // dieses Repository soll einbezogen werden
                $repositories[$displayName] = $mainPath . DIRECTORY_SEPARATOR . $path;
            }
        }

        function get_filename($name) {
            /* if (substr($name,0,1) === "\""){

              } */
            return trim(trim($name, "\""));
        }

        $allCommits = array();
        $allDummys = array();
        $allTags = array();
        foreach ($repositories as $repoName => $repo) {
            $res['repos'][$repoName] = array();
            $pathOld = getcwd();
            $out = null;
            @chdir($repo);
            exec('(git log --decorate=full --stat --name-status --date=raw --pretty=format:\'%ad,%ae,%an,%d\' -M75% -C75% --all --no-notes) 2>&1', $out, $return);
            @chdir($pathOld);

            $res['repos'][$repoName]['logStatus'] = $return;
            if ($return !== 0) {
                continue;
            }

            $authorMap = array();
            $excludeList = array();
            $excludeContainsList = array();
            if (file_exists(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'defs' . DIRECTORY_SEPARATOR . $repoName . '.json')) {
                $tmp = json_decode(file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'defs' . DIRECTORY_SEPARATOR . $repoName . '.json'), true);
                if (isset($tmp['authorMap'])) {
                    $authorMap = $tmp['authorMap'];
                }
                if (isset($tmp['exclude'])) {
                    $excludeList = $tmp['exclude'];
                }
                if (isset($tmp['excludeContains'])) {
                    $excludeContainsList = $tmp['excludeContains'];
                }
            }

            $commit = null;
            $commits = array();
            $authors = array();
            $anz = count($out);
            $tags = array();
            for ($i = 0; $i < $anz; $i++) {
                $out[$i] = trim($out[$i], "'");
                $commit = array('changes' => array());
                $line = explode(',', $out[$i]);

                $o = explode(' ', $line[0]);
                $commit['date'] = $o[0];

                $commit['author']['mail'] = $line[1];
                $commit['author']['name'] = $line[2];
                if (isset($line[3]) && !empty($line[3])) {
                    $line[3] = implode(',', array_slice($line, 3));
                    $startsAt = strpos($line[3], "refs/tags/");

                    if ($startsAt !== false) {
                        $startsAt += strlen("refs/tags/");
                        $endsAt = strpos($line[3], ")", $startsAt);
                        if ($endsAt !== false) {
                            $result = substr($line[3], $startsAt, $endsAt - $startsAt);
                            $result = explode(',', $result);
                            $tags[] = array('date' => $o[0], 'name' => $repoName . ': ' . str_replace('_', ' ', $result[0]));
                        } else {
                            $result = substr($line[3], $startsAt);
                            $result = explode(',', $result);
                            $tags[] = array('date' => $o[0], 'name' => $repoName . ': ' . str_replace('_', ' ', $result[0]));
                        }
                    }
                }

                if (isset($authorMap[$commit['author']['mail']])) {
                    $a = $authorMap[$commit['author']['mail']];
                    $commit['author']['name'] = $a[0];
                    $commit['author']['mail'] = $a[1];
                }
                $authors[$commit['author']['name'] . '_' . $commit['author']['mail']] = $commit['author'];

                $b = $i + 1;
                $ignoreChanges = false;
                for (; $b < $anz; $b++) {
                    if (strlen($out[$b]) === 0) {
                        break;
                    }
                    $indikator = substr($out[$b], 0, 1);
                    if ($indikator === '\'') {
                        $b--;
                        break;
                    } elseif ($indikator !== ' ' && !$ignoreChanges) {
                        $p = substr($out[$b], 1);
                        if ($indikator === 'R' || $indikator === 'C') {
                            $p = explode("\t", $p);
                            $p[2] = get_filename($p[2]);
                            $p[1] = get_filename($p[1]);
                            if (self::filter($indikator, $p[2], $excludeList, $excludeContainsList)) {
                                $commit['changes'][] = array('type' => 'A', 'file' => $p[2]);
                            }
                            if (self::filter($indikator, $p[1], $excludeList, $excludeContainsList)) {
                                $commit['changes'][] = array('type' => 'D', 'file' => $p[1]);
                            }
                        } else {
                            $p = trim($p);
                            $p = get_filename($p);
                            if (self::filter($indikator, $p, $excludeList, $excludeContainsList)) {
                                $commit['changes'][] = array('type' => $indikator, 'file' => $p);
                            }
                        }
                    }
                }
                $i = $b;
                $commit['changes'] = array_reverse($commit['changes']);
                if (!empty($commit['changes'])) {
                    $commit['repo'] = $repoName;
                    $commits[] = $commit;
                }
            }
            unset($out);
            if ($commit !== null && isset($commits['author'])) {
                $commits[] = $commit;
            }
            unset($commit);
            //$last = $commits[count($commits)-1];
            $allDummys[] = array('date' => 0, 'author' => array('name' => ''), 'repo' => $repoName, 'changes' => array(array('type' => 'D', 'file' => 'dummy')));
            $commits = array_reverse($commits);
            ///file_put_contents($location. DIRECTORY_SEPARATOR .$repoName.'_authors.json',json_encode($authors));
            unset($authors);
            $allCommits = array_merge($allCommits, $commits);

            $allTags = array_merge($allTags, $tags);
        }


        // nun müssen alle Commits aufsteigend sortiert werden
        usort($allCommits, function ($a, $b) {
            return $a['date'] > $b['date'];
        });
        
        // hier werden die Commits aussortiert
        $beginTimestamp = '0';
        if (isset($data['GOURCE']['beginTimestamp'])){
            $beginTimestamp = strtotime($data['GOURCE']['beginTimestamp']);
        }
        
        $initFiles = array();
        
        foreach($allCommits as $key => $commit){
            if (intval($commit['date']) < intval($beginTimestamp)){
                foreach ($commit['changes'] as $change) {
                    if ($change['type'] == 'A' || $change['type'] == 'M') {
                        $initFiles[$commit['repo'].$change['file']] = array($commit['repo'], $change['file']);
                        continue;
                    }
                    if ($change['type'] == 'D') {
                        unset($initFiles[$commit['repo'].$change['file']]);
                        continue;
                    }
                }
                unset($allCommits[$key]);
            } else {
                break;
            }            
        }
        
        $allCommits = array_values($allCommits);
        
        $first = $allCommits[0];
        
        foreach($initFiles as $key => $file){
            $allDummys[] = array('date' => 0, 'author' => array('name' => ''), 'repo' => $file[0], 'changes' => array(array('type' => 'A', 'file' => $file[1])));
        }

        foreach ($allDummys as $key => $dummy) {
            $allDummys[$key]['date'] = $first['date']; // + $key
            $allDummys[$key]['author']['name'] = $first['author']['name'];
        }
        $allCommits = array_merge($allDummys, $allCommits);

        // nun müssen alle Tags aufsteigend sortiert werden
        usort($allTags, function ($a, $b) {
            return $a['date'] > $b['date'];
        });

        // commits umwandeln in das gource format
        $result = array();
        foreach ($allCommits as $commit) {
            foreach ($commit['changes'] as $change) {
                $dat = array($commit['date'], $commit['author']['name'], $change['type'], $commit['repo'] . '/' . $change['file']);
                $result[] = implode('|', $dat);
            }
        }

        // tags umwandeln in das gource format
        $tagResult = array();
        foreach ($allTags as $tag) {
            $dat = array($tag['date'], $tag['name']);
            $tagResult[] = implode('|', $dat);
        }

        $timestamp = date('Ymd_His');
        $filename = $location . DIRECTORY_SEPARATOR . 'gource_' . $timestamp . '.dat';
        file_put_contents($filename, implode("\n", $result));
        $res['outputFile'] = $filename;
        $res['outputSize'] = filesize($filename);

        $filenameTag = $location . DIRECTORY_SEPARATOR . 'gource_' . $timestamp . '.captions';
        file_put_contents($filenameTag, implode("\n", $tagResult));
        $res['outputFileTag'] = $filenameTag;
        $res['outputSizeTag'] = filesize($filenameTag);

        Installation::log(array('text' => Installation::Get('main', 'functionEnd')));
        return $res;
    }

    public static function installListGourceData($data, &$fail, &$errno, &$error) {
        Installation::log(array('text' => Installation::Get('main', 'functionBegin')));
        $res = array();
        if (is_dir($data['GOURCE']['path'])) {
            $files = Installation::read_all_files($data['GOURCE']['path']);
            $res['gourceData'] = array();
            foreach ($files['files'] as $file) {
                if (pathinfo($file)['extension'] === 'dat') {
                    $inpData = array();
                    $inpData['file'] = $file;
                    $inpData['size'] = filesize($file);
                    $res['gourceData'][] = $inpData;
                }
            }
        }

        Installation::log(array('text' => Installation::Get('main', 'functionEnd')));
        return $res;
    }

    public static function installListGourceResult($data, &$fail, &$errno,
            &$error) {
        Installation::log(array('text' => Installation::Get('main', 'functionBegin')));
        $res = array();
        if (is_dir($data['GOURCE']['path'])) {
            $files = Installation::read_all_files($data['GOURCE']['path']);
            $res['gourceResult'] = array();
            foreach ($files['files'] as $file) {
                if (pathinfo($file)['extension'] === 'ppm') {
                    $data = array();
                    $data['file'] = $file;
                    $data['size'] = @filesize($file);
                    $res['gourceResult'][] = $data;
                }
            }
        }

        Installation::log(array('text' => Installation::Get('main', 'functionEnd')));
        return $res;
    }

    public static function installExecuteGource($data, &$fail, &$errno, &$error) {
        Installation::log(array('text' => Installation::Get('main', 'functionBegin')));
        $res = array();
        $file = $data['GOURCE']['selectedData'];
        $dir = dirname($file);
        $outputFile = $dir . DIRECTORY_SEPARATOR . pathinfo($file)['filename'] . '.ppm';
        $tagFile = $dir . DIRECTORY_SEPARATOR . pathinfo($file)['filename'] . '.captions';

        $exec = 'gource --path "' . $file . '" --caption-file ' . $tagFile . ' --load-config "' . dirname(__FILE__) . DIRECTORY_SEPARATOR . 'gource.conf" -o "' . $outputFile . '"';
        Installation::execInBackground($exec);

        Installation::log(array('text' => Installation::Get('main', 'functionEnd')));
        return $res;
    }

    public static function installExecuteGourceOnly($data, &$fail, &$errno,
            &$error) {
        Installation::log(array('text' => Installation::Get('main', 'functionBegin')));
        $res = array();
        $file = $data['GOURCE']['selectedData'];
        $dir = dirname($file);
        $tagFile = $dir . DIRECTORY_SEPARATOR . pathinfo($file)['filename'] . '.captions';

        $exec = 'gource --path "' . $file . '" --caption-file ' . $tagFile . ' --load-config "' . dirname(__FILE__) . DIRECTORY_SEPARATOR . 'gource.conf"';
        Installation::execInBackground($exec);

        Installation::log(array('text' => Installation::Get('main', 'functionEnd')));
        return $res;
    }

    public static function installConvertGource($data, &$fail, &$errno, &$error) {
        Installation::log(array('text' => Installation::Get('main', 'functionBegin')));
        $res = array();
        $file = $data['GOURCE']['selectedResult'];
        $dir = dirname($file);
        $outputFile = $dir . DIRECTORY_SEPARATOR . pathinfo($file)['filename'] . '.mp4';

        $exec = 'ffmpeg -y -r 60 -f image2pipe -vcodec ppm -i "' . $file . '" -vcodec libx264 -preset ultrafast -pix_fmt yuv420p -crf 1 -threads 4 -bf 0 "' . $outputFile . '"';
        Installation::execInBackground($exec);

        Installation::log(array('text' => Installation::Get('main', 'functionEnd')));
        return $res;
    }

}

#endregion BackupSegment