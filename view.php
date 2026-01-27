<?php

function parse_story_to_html($json, $selectedLang = 'English'): string {

    $playerName = trim($_POST['playerName'] ?? '');

    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return "<div class='error'>Invalid JSON: " . htmlspecialchars(json_last_error_msg()) . "</div>";
    }
    
    if (empty($data['m_Structure']['importGridList'])&&empty($data['importGridList'])) {
        return "<div class='error'>Missing important structure!</div>";
    }
  
    // 🔍 Auto-detect language column index dynamically
$languageIndex = "Japanese"; // default fallback
$firstGrid = $data['m_Structure']['importGridList'][0] 
    ?? $data['importGridList'][0];

$header = $firstGrid['rows'][0]['strings'] ?? [];
$headerMap = array_flip($header);

// Detect Voice column
$voiceIndex = null;
if (isset($headerMap['Voice'])) {
    $voiceIndex = $headerMap['Voice'];
}


if (isset($headerMap[$selectedLang])) {
    $languageIndex = $headerMap[$selectedLang];
} elseif (isset($headerMap['English'])) {
    $languageIndex = $headerMap['English'];
}


    $html = "<ol>\n";
    foreach ($data['m_Structure']['importGridList'] ?? $data['importGridList'] as $grid) {
        $rawName = $grid['name'] ?? 'Unnamed';
    // Extract substring after '.xls:'
    if (strpos($rawName, '.xls:') !== false) {
        $parts = explode('.xls:', $rawName);
        $rawName = $parts[1];
    }

    $shortName = preg_match('/\*?(quest_[A-Za-z0-9_\-]+)/', $rawName, $matches) ? $matches[1] : $rawName;
    $name = htmlspecialchars($shortName);
    $html .= "<li><a href='#$name'>$name</a></li>\n";
}
    $html .= "</ol>\n";

    foreach ($data['m_Structure']['importGridList'] ?? $data['importGridList'] as $grid) {
        $rawGridName = $grid['name'] ?? 'Unnamed';
        if (strpos($rawGridName, '.xls:') !== false) {
    $parts = explode('.xls:', $rawGridName);
    $rawGridName = $parts[1];
}
        $shortGridName = preg_match('/quest_[A-Za-z0-9_\-]+/', $rawGridName, $matches) ? $matches[0] : $rawGridName;
        $gridName = htmlspecialchars($shortGridName);
        $html .= "<h3 id='$gridName'>$gridName</h3>\n";

        foreach ($grid['rows'] ?? [] as $row) {
            if (!empty($row['isEmpty']) || !empty($row['isCommentOut']) || ($row['rowIndex'] === 0)) continue;

            $strings = $row['strings'] ?? [];
            if (empty($strings)) continue;

            $cmd = trim($strings[0] ?? '');

            if (str_starts_with($cmd, '*')) {
                $label = htmlspecialchars(ltrim($cmd, '*'));
                $html .= "<div class='label' id='$label'>Label: $label</div>\n";
            }

if ($cmd === "Selection") {
    $text = $strings[$languageIndex] ?? '[No Text]';
    $text = str_replace(['\\u003c', '\\u003e'], ['<', '>'], $text);
if ($playerName !== '') {
    $text = str_replace(
        ['<param=playerName>', '<param=pronounTradChineseName1>', '<param=pronounTradChineseName2>', '<param=teamLeaderCharaName>'],
        $playerName,
        $text
    );
}


    // Process ruby tags
    $text = preg_replace_callback('/<ruby=(.*?)>(.*?)<\/ruby>/', function ($matches) {
        $rt = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
        $rb = htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8');
        return "<ruby>{$rb}<rp>(</rp><rt>{$rt}</rt><rp>)</rp></ruby>";
    }, $text);

    // Process emphasis tags
    $text = preg_replace_callback('/<em=(.*?)>(.*?)<\/em>/', function ($matches) {
        $mark = str_replace('.', '・', $matches[1]);
        $mark = htmlspecialchars($mark, ENT_QUOTES, 'UTF-8');
        $content = htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8');
        $chars = preg_split('//u', $content, -1, PREG_SPLIT_NO_EMPTY);
        $result = '';
        foreach ($chars as $char) {
            $result .= "<ruby>{$char}<rt>{$mark}</rt></ruby>";
        }
        return $result;
    }, $text);

    // Strip <speed> tags
    $text = preg_replace(['/<speed=([\d.]+)>/', '/<\/speed>/'], '', $text);

    // Escape entire text first
    // Protect <param=...> tags from being stripped
$text = preg_replace('/<param=([^>]+)>/', '&lt;param=\1&gt;', $text);


    // Restore ruby-related tags
    $text = str_replace(
        ['&lt;br&gt;', '&lt;ruby&gt;', '&lt;/ruby&gt;', '&lt;rt&gt;', '&lt;/rt&gt;', '&lt;rp&gt;', '&lt;/rp&gt;'],
        ['<br>', '<ruby>', '</ruby>', '<rt>', '</rt>', '<rp>', '</rp>'],
        $text
    );

    // Restore <span style="font-size">
    $text = preg_replace_callback('/&lt;size=(\d+)&gt;(.*?)&lt;\/size&gt;/s', function ($matches) {
        $originalSize = intval($matches[1]);
        $adjustedSize = max(1, $originalSize - 15);
        $content = $matches[2];
        return "<span style=\"font-size:{$adjustedSize}px\">{$content}</span>";
    }, $text);

    // Line breaks
    $text = str_replace(["\\n", "\r\n", "\n", "\r"], "<br>", $text);
    $condition = trim($strings[2] ?? '');
    $label = htmlspecialchars(ltrim($strings[1] ?? '', '*'));
    
    
        // Output HTML
    if (!empty($condition)) {
        $html .= "<div class='select'><a href='#$label'>$text</a> <span class='cond'>( " . htmlspecialchars($condition) . " )</span></div>\n";
    } else {
        $html .= "<div class='select'><a href='#$label'>$text</a></div>\n";
    }

            } elseif ($cmd === "Label") {
                $label = htmlspecialchars(ltrim($strings[1] ?? '', '*'));
                $html .= "<div class='label' id='$label'>Label: $label</div>\n";
            } elseif ($cmd === "Jump") {
 $dest = htmlspecialchars(ltrim($strings[1] ?? '', '*'));
    $condition = trim($strings[2] ?? '');

    // If there is a condition, display it inside brackets
    if (!empty($condition)) {
        $html .= "<div class='jump'>Jump to <a href='#$dest'>$dest</a> <span class='cond'>( " . htmlspecialchars($condition) . " )</span></div>\n";
    } else {
        $html .= "<div class='jump'>Jump to <a href='#$dest'>$dest</a></div>\n";
    }
            } elseif ($cmd === "Bg") {
                $text = htmlspecialchars($strings[1] ?? '');
                $html .= "<p>Background: $text</p>\n";
            } elseif ($cmd === "Bgm") {
                $text = htmlspecialchars($strings[1] ?? '');
                $html .= "<p>BGM: $text</p>\n";
            } else {
                $chara = trim($strings[1] ?? '');
                $emotion = trim($strings[2] ?? '');
                $text = trim($strings[$languageIndex] ?? '');

                if (!empty($text)) {
                    $text = str_replace(['\\u003c', '\\u003e'], ['<', '>'], $text);
if ($playerName !== '') {
    $text = str_replace(
        ['<param=playerName>', '<param=pronounTradChineseName1>', '<param=pronounTradChineseName2>', '<param=teamLeaderCharaName>'],
        $playerName,
        $text
    );
}


                    $text = preg_replace_callback('/<ruby=(.*?)>(.*?)<\/ruby>/', function ($matches) {
                        $rt = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
                        $rb = htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8');
                        return "<ruby>{$rb}<rp>(</rp><rt>{$rt}</rt><rp>)</rp></ruby>";
                    }, $text);

                    $text = preg_replace_callback('/<em=(.*?)>(.*?)<\/em>/', function ($matches) {
                        $mark = str_replace('.', '・', $matches[1]);
     $mark = htmlspecialchars($mark, ENT_QUOTES, 'UTF-8');
    $content = htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8');
    $chars = preg_split('//u', $content, -1, PREG_SPLIT_NO_EMPTY);
    $result = '';
    foreach ($chars as $char) {
        $result .= "<ruby>{$char}<rt>{$mark}</rt></ruby>";
    }
    return $result;
}, $text);

                    
// Remove <speed=...> and </speed> tags
$text = preg_replace(['/<speed=([\d.]+)>/', '/<\/speed>/'], '', $text);
// Replace <size=...>...</size> with <span style="font-size:...px">...</span>
// Escape HTML first
// Protect <param=...> tags from being stripped
$text = preg_replace('/<param=([^>]+)>/', '&lt;param=\1&gt;', $text);



// Restore allowed tags like <br>, <ruby>, etc.
$text = str_replace(
    ['&lt;br&gt;', '&lt;ruby&gt;', '&lt;/ruby&gt;', '&lt;rt&gt;', '&lt;/rt&gt;', '&lt;rp&gt;', '&lt;/rp&gt;'],
    ['<br>', '<ruby>', '</ruby>', '<rt>', '</rt>', '<rp>', '</rp>'],
    $text
);

// Restore <span style="font-size:...px"> by parsing <size=...> inside already-escaped text
$text = preg_replace_callback('/&lt;size=(\d+)&gt;(.*?)&lt;\/size&gt;/s', function ($matches) {
    $originalSize = intval($matches[1]);
    $adjustedSize = max(1, $originalSize - 15); // avoid zero or negative size
    $content = $matches[2];
    return "<span style=\"font-size:{$adjustedSize}px\">{$content}</span>";
}, $text);


                    $text = str_replace(
                        ['&lt;br&gt;', '&lt;ruby&gt;', '&lt;/ruby&gt;', '&lt;rt&gt;', '&lt;/rt&gt;', '&lt;rp&gt;', '&lt;/rp&gt;'],
                        ['<br>', '<ruby>', '</ruby>', '<rt>', '</rt>', '<rp>', '</rp>'],
                        $text
                    );

                    $text = str_replace(["\\n", "\r\n", "\n", "\r"], "<br>", $text);
$voice = ($voiceIndex !== null) ? trim($strings[$voiceIndex] ?? '') : "";


// Prepare voice HTML (gray, in brackets)
$voiceTag = "";
if (!empty($voice)) {
    $voiceEscaped = htmlspecialchars($voice, ENT_QUOTES, 'UTF-8');
    $voiceTag = " <span class='voice'>[{$voiceEscaped}]</span>";
}
                    if (!empty($chara)) {
                        $label = $emotion ? "$chara ($emotion)" : $chara;
                        $html .= "<div class='text'><span class='chara'>$label:</span> $text$voiceTag</div>\n";
                    } else {
                        $html .= "<div class='text narration'>$text$voiceTag</div>\n";
                    }
                }
            }
        }
    }

    return $html;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Parsed Story</title>
<style>
h3 {
  top: 0;
  position: sticky;
  background: #7986CB;
  padding: 5px;
}
.select {
  background: #8888ff;
  color: #fff;
}
.select a {
  color: #fff !important;
  text-decoration: none;
}
.select .cond {
  color: #fff;

}
.jump {
  background: #88ff88;
}
.label {
  background: #ffff88;
}
.label:target {
  background: #ff8888;
}
.chara {
  font-weight: 900;
}
.text {
  background: #f5f5f5;
}
.title {
  background: #88ffff;
}
.voice {
  color: #7f7f7f;
}
.select, .jump, .label, .text, textarea, .title {
  margin: 10px;
  padding: 10px;
}
.cond-block {
  margin-inline-start: 2px;
  margin-inline-end: 2px;
  padding-block-start: 0.35em;
  padding-inline-start: 0.75em;
  padding-inline-end: 0.75em;
  padding-block-end: 0.625em;
  min-inline-size: min-content;
  border: 2px groove threedface;
}
.cond {
  padding-inline-start: 2px;
  padding-inline-end: 2px;
  border-width: initial;
  border-style: none;
  border-color: initial;
  border-image: initial;
}
.effect {
  padding: 5px;
  line-height: 2;
  white-space: nowrap;
  border-radius: 5px;
  background: #888888;
  color: #fff;
}
em {
  text-emphasis: circle;
  -webkit-text-emphasis: circle;
  font-style: normal;
}
.hide-br br {
  display: none;
}
.hide-ruby rt {
  display: none;
}
.hide-ruby ruby {
text-shadow: 0 0 8px #ee00ee;
  background: #ffaaff;
}
.control {
  position: fixed;
  top: 15px;
  right: 10px;
  background: #fff;
}

</style>
</head>
<body>

<fieldset class="control">
<legend>Control</legend>
<input type="checkbox" id="br-btn"><label for="br-btn">Hide line break</label><br>
<input type="checkbox" id="ruby-btn"><label for="ruby-btn">Hide ruby text</label><br>
<button type="button" onclick="window.history.back()" style="margin-top:5px;">🔙 Back</button>
</fieldset>


<h2>📖 Parsed Story</h2>
<button id="saveHtmlBtn" style="margin: 10px; padding: 8px 15px;">💾 Save as HTML</button>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Check if a file was uploaded
    if (isset($_FILES['jsonFile']) && $_FILES['jsonFile']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['jsonFile']['tmp_name'];
        $jsonContent = file_get_contents($fileTmpPath);

        // Optional: decode JSON to verify it's valid
        $data = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            die("Invalid JSON file.");
        }

        // Get other form inputs
        $language = $_POST['language'] ?? '';
echo parse_story_to_html($jsonContent, $language);

} else {
    if (!isset($_FILES['jsonFile'])) {
        echo "No jsonFile field received.";
    } else {
        echo "Upload error code: " . $_FILES['jsonFile']['error'];
    }
}

} else {
    echo "Invalid request method.";
}
?>


<script>
document.querySelector("#br-btn").onclick = function() {
  document.body.classList.toggle("hide-br");
};
document.querySelector("#ruby-btn").onclick = function() {
  document.body.classList.toggle("hide-ruby");
};
</script>
<script>
// Save current page content as a standalone HTML file
document.getElementById("saveHtmlBtn").addEventListener("click", function () {
    const fullHtml =
        "<!DOCTYPE html>\n<html>\n" +
        document.documentElement.innerHTML +
        "\n</html>";

    const blob = new Blob([fullHtml], { type: "text/html" });
    const url = URL.createObjectURL(blob);

    const link = document.createElement("a");
    link.href = url;
    link.download = "story_output.html";
    link.click();

    URL.revokeObjectURL(url);
});
</script>


</body>
</html>
