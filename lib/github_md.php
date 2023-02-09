<?php
class rex_feeds_stream_github_repo extends rex_feeds_stream_abstract
{
    public function getTypeName()
    {
        # Name des Streams
        return rex_i18n::msg('feeds_github_repo');
    }
    public function getTypeParams()
    {
        # Eingabefelder für die Konfiguration des Streams
        return [
            [
                'label' => rex_i18n::msg('feeds_github_repo_name'),
                'name' => 'repo_name',
                'type' => 'string',
            ],
            [
                'label' => rex_i18n::msg('feeds_github_subrepo_name'),
                'name' => 'subrepo_name',
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
        # Falls ein Unterordner angegeben wurde, wird dieser in die URL eingebaut
        if ($this->typeParams['subrepo_name'] == "") {
            $url = "https://api.github.com/repos/" . $this->typeParams['repo_name'] . "/" . "contents?ref=" . $this->typeParams['branch'];
        } else {
            $url = "https://api.github.com/repos/" . $this->typeParams['repo_name'] . "/" . $this->typeParams['subrepo_name'] . "/" . "contents?ref=" . $this->typeParams['branch'];
        }

        # Die Daten werden als JSON-Array zurückgegeben
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

        # mit $dump werden die Daten in der Konsole ausgegeben
        dump($contents);

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
