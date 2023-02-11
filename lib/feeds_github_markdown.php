<?php
class rex_feeds_stream_github_markdown extends rex_feeds_stream_abstract
{
    public function getTypeName()
    {
        # Name des Streams
        return rex_i18n::msg('feeds_github_md');
    }
    public function getTypeParams()
    {
        # Eingabefelder für die Konfiguration des Streams
        return [
            [
                'label' => rex_i18n::msg('feeds_github_user_name'),
                'name' => 'user_name',
                'type' => 'string',
            ],
            [
                'label' => rex_i18n::msg('feeds_github_repo_name'),
                'name' => 'repo_name',
                'type' => 'string',
            ],
            [
                'label' => rex_i18n::msg('feeds_github_branch'),
                'name' => 'branch',
                'type' => 'string',
                'default' => 'main',
            ],
        ];
    }
    public function fetch()
    {
        # Hier wird die API abgefragt und die Daten in die Datenbank geschrieben
        # Falls ein Unterordner angegeben wurde, wird dieser in die URL eingebaut, sonst wird der Root-Ordner abgefragt
        $url = "";

        if ($this->typeParams['repo_name'] == "") {
            $url = "https://api.github.com/repos/" . $this->typeParams['user_name'] . "/" . "contents?ref=" . $this->typeParams['branch'];
        } else {
            $url = "https://api.github.com/repos/" . $this->typeParams['user_name'] . "/" . $this->typeParams['repo_name'] . "/" . "contents?ref=" . $this->typeParams['branch'];
        }

        # Prüfen ob die URL erreichbar ist
        $options = array(
            'http' => array(
                'method' => 'HEAD',
                'header' => array(
                    "User-Agent: PHP\r\n"
                )
            )
        );
        $context = stream_context_create($options);
        $head = @get_headers($url, 1, $context);
        # Wenn die URL nicht erreichbar ist, wird die Funktion abgebrochen
        if (strpos($head[0], 'HTTP/1.1 404 Not Found') !== false || strpos($head[0], 'HTTP/1.1 403 Forbidden') !== false) {
            return;
        } else if (strpos($head[0], 'HTTP/1.1 200 OK') !== false) {
            # Wenn die URL erreichbar ist, werden die Daten abgefragt
            $options = array(
                'http' => array(
                    'method' => 'GET',
                    'header' => array(
                        "User-Agent: PHP\r\n"
                    )
                )
            );
            $context = stream_context_create($options);
            $contents = file_get_contents($url, false, $context);
            $contents = json_decode($contents, true);
            # Wenn die Daten erfolgreich abgefragt wurden, werden sie in die Datenbank geschrieben
            if (is_array($contents) && !empty($contents)) {
                # Nur Dateien mit der Endung .md werden importiert
                foreach ($contents as $content) {
                    if (substr($content['name'], -3) == ".md") {
                        $item = new rex_feeds_item($this->streamId, $content['sha']);
                        $item->setContentRaw(file_get_contents($content['download_url']));
                        $item->setContent(strip_tags(file_get_contents($content['download_url'])));
                        $item->setUrl($content['html_url']);
                        $item->setDate(new DateTime());
                        $item->setRaw($content);
                        $this->updateCount($item);
                        $item->save();
                    }
                }
                self::registerExtensionPoint($this->streamId);
            }
        }
    }
}
