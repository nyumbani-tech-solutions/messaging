<?php

namespace Nyumbani\Messaging\Support;

class EmailLines
{
    /**
     * Split a plain text body into the typed lines an email template renders: text, link or break
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    public static function from(?string $body): array
    {
        return collect(explode("\n", rtrim((string) $body, "\n")))
            ->reject(fn (string $line) => stripos($line, 'Subject:') !== false)
            ->map(fn (string $line) => static::line($line))
            ->values()
            ->all();
    }

    /**
     * Turn one line into its typed form
     *
     * @author Alvin G. Kaburu <geekaburu@nyumbanitech.co.ke>
     */
    protected static function line(string $line): array
    {
        //  A blank line is a break
        if (trim($line) === '') {
            return ['break' => true];
        }

        //  Check if the line is a link
        $isLink = preg_match("/\b((https?|ftp|file):\/\/|www\.)[-A-Z0-9+&@#\/%?=~_|$!:,.;]*[A-Z0-9+&@#\/%=~_|$]/i", $line, $matches);

        //  A line with a link becomes an action: the text before the link labels it
        if ($isLink) {
            //  Get the label, which falls back to a generic one
            $action = trim(str_replace(':', '', strstr($line, $matches[0], true)));

            //  Return the action
            return [
                'link' => [
                    'action' => $action ?: 'Click Here', 
                    'url' => $matches[0]
                ]
            ];
        }

        //  Any other line is text
        return ['text' => $line];
    }
}
