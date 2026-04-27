<?php
/**
 * Convert limited BBCode to safe HTML.
 * Supported tags:
 * [b], [i], [u], [h2], [h3], [p], [ul], [li]
 *
 * Security: raw HTML is escaped, only BBCode tags are transformed.
 */
function bbcode_to_html($text)
{
    $text = (string)$text;
    // Escape HTML first to prevent injection; BBCode markers ([...]) stay intact.
    $html = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // Simple inline tags.
    $html = preg_replace('/\[b\](.*?)\[\/b\]/is', '<strong>$1</strong>', $html);
    $html = preg_replace('/\[i\](.*?)\[\/i\]/is', '<em>$1</em>', $html);
    $html = preg_replace('/\[u\](.*?)\[\/u\]/is', '<u>$1</u>', $html);

    // Headings.
    $html = preg_replace('/\[h2\](.*?)\[\/h2\]/is', '<h2>$1</h2>', $html);
    $html = preg_replace('/\[h3\](.*?)\[\/h3\]/is', '<h3>$1</h3>', $html);

    // Paragraph tags.
    $html = preg_replace('/\[p\](.*?)\[\/p\]/is', '<p>$1</p>', $html);

    // Lists.
    // Convert [li] to <li> and wrap [ul]...</ul>.
    $html = preg_replace('/\[\/ul\]/is', '</ul>', $html);
    $html = preg_replace('/\[ul\]/is', '<ul>', $html);
    $html = preg_replace('/\[\*\](.*?)($|\n)/is', '<li>$1</li>$2', $html);
    $html = preg_replace('/\[li\](.*?)\[\/li\]/is', '<li>$1</li>', $html);

    // Keep line breaks for plain text.
    $html = nl2br($html);

    return $html;
}

