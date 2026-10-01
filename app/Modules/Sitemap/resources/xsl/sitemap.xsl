<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:s="http://www.sitemaps.org/schemas/sitemap/0.9">
    <xsl:output method="html" encoding="UTF-8" indent="yes" doctype-system="about:legacy-compat" />

    <xsl:template match="/">
        <html lang="en">
            <head>
                <meta charset="utf-8" />
                <meta name="viewport" content="width=device-width, initial-scale=1" />
                <title>
                    <xsl:choose>
                        <xsl:when test="s:sitemapindex">Sitemap Index</xsl:when>
                        <xsl:otherwise>Sitemap</xsl:otherwise>
                    </xsl:choose>
                    <xsl:text> — Draft CMS</xsl:text>
                </title>
                <link rel="icon" href="/favicon.png" sizes="any" />
                <style>
                    :root {
                        --zinc-50: #fafafa; --zinc-100: #f5f5f5; --zinc-200: #e5e5e5; --zinc-300: #d4d4d4;
                        --zinc-400: #a3a3a3; --zinc-500: #737373; --zinc-600: #525252; --zinc-700: #404040;
                        --zinc-800: #262626; --zinc-900: #171717; --zinc-950: #0a0a0a;
                        --bg: #ffffff; --panel: var(--zinc-50); --border: var(--zinc-200);
                        --text: var(--zinc-900); --text-muted: var(--zinc-500);
                        --accent-bg: var(--zinc-800); --accent-fg: #ffffff;
                    }
                    @media (prefers-color-scheme: dark) {
                        :root {
                            --bg: var(--zinc-900); --panel: var(--zinc-950); --border: var(--zinc-700);
                            --text: var(--zinc-100); --text-muted: var(--zinc-400);
                            --accent-bg: #ffffff; --accent-fg: var(--zinc-900);
                        }
                    }
                    * { box-sizing: border-box; }
                    body {
                        margin: 0;
                        font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';
                        background: var(--bg);
                        color: var(--text);
                    }
                    .wrap { max-width: 72rem; margin: 0 auto; padding: 2.5rem 1.5rem 4rem; }
                    header.top { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 2rem; }
                    .brand-icon {
                        display: flex; align-items: center; justify-content: center;
                        width: 2.25rem; height: 2.25rem; border-radius: 0.5rem; flex-shrink: 0;
                        background: var(--accent-bg); color: var(--accent-fg);
                    }
                    .brand-icon svg { width: 1.25rem; height: 1.25rem; }
                    .brand-name { font-weight: 600; font-size: 0.9375rem; letter-spacing: -0.01em; }
                    .brand-sub { color: var(--text-muted); font-size: 0.8125rem; }
                    h1 { font-size: 1.375rem; font-weight: 600; letter-spacing: -0.01em; margin: 0 0 0.375rem; }
                    p.lead { color: var(--text-muted); font-size: 0.875rem; margin: 0 0 1.75rem; }
                    .count-badge {
                        display: inline-flex; align-items: center; gap: 0.375rem;
                        font-size: 0.75rem; color: var(--text-muted);
                        border: 1px solid var(--border); border-radius: 9999px;
                        padding: 0.25rem 0.75rem; margin-bottom: 1.5rem;
                    }
                    a.back { color: var(--text-muted); font-size: 0.8125rem; text-decoration: none; }
                    a.back:hover { text-decoration: underline; }
                    table {
                        width: 100%; border-collapse: collapse;
                        border: 1px solid var(--border); border-radius: 0.75rem;
                        overflow: hidden; font-size: 0.8125rem;
                    }
                    thead th {
                        text-align: left; font-weight: 500; color: var(--text-muted);
                        background: var(--panel); border-bottom: 1px solid var(--border);
                        padding: 0.625rem 1rem; white-space: nowrap;
                    }
                    tbody td {
                        padding: 0.75rem 1rem; border-bottom: 1px solid var(--border);
                        vertical-align: middle;
                    }
                    tbody tr:last-child td { border-bottom: none; }
                    tbody tr:hover td { background: var(--panel); }
                    td.loc a { color: var(--text); text-decoration: none; word-break: break-all; }
                    td.loc a:hover { text-decoration: underline; }
                    td.muted, .muted { color: var(--text-muted); }
                    .pill {
                        display: inline-block; font-size: 0.6875rem; font-weight: 500;
                        border: 1px solid var(--border); border-radius: 9999px;
                        padding: 0.0625rem 0.5rem; color: var(--text-muted); white-space: nowrap;
                    }
                    footer.note {
                        margin-top: 2rem; font-size: 0.75rem; color: var(--text-muted);
                        border-top: 1px solid var(--border); padding-top: 1rem;
                    }
                    footer.note code {
                        background: var(--panel); border: 1px solid var(--border);
                        border-radius: 0.25rem; padding: 0.05rem 0.35rem; font-size: 0.6875rem;
                    }
                    .empty { color: var(--text-muted); font-size: 0.875rem; padding: 2rem 0; text-align: center; }
                </style>
            </head>
            <body>
                <div class="wrap">
                    <header class="top">
                        <span class="brand-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
                                <circle cx="6" cy="5" r="3" />
                                <circle cx="6" cy="19" r="3" />
                                <circle cx="18" cy="19" r="3" />
                                <path d="M6 8v8m12-6v1m0-7v1" />
                            </svg>
                        </span>
                        <span>
                            <span class="brand-name">Draft CMS</span><br />
                            <span class="brand-sub">Sitemap</span>
                        </span>
                    </header>

                    <xsl:choose>
                        <xsl:when test="s:sitemapindex">
                            <xsl:call-template name="index" />
                        </xsl:when>
                        <xsl:otherwise>
                            <xsl:call-template name="urlset" />
                        </xsl:otherwise>
                    </xsl:choose>

                    <footer class="note">
                        This is an XML sitemap generated automatically for search engines — see <code>/sitemap.xml</code>. It's styled here for readability; crawlers read the raw XML.
                    </footer>
                </div>
            </body>
        </html>
    </xsl:template>

    <xsl:template name="index">
        <h1>Sitemap Index</h1>
        <p class="lead">One sitemap file per collection. Search engines start here and follow each link below.</p>
        <span class="count-badge">
            <xsl:value-of select="count(s:sitemapindex/s:sitemap)" /> collection<xsl:if test="count(s:sitemapindex/s:sitemap) != 1">s</xsl:if>
        </span>

        <xsl:choose>
            <xsl:when test="count(s:sitemapindex/s:sitemap) = 0">
                <div class="empty">No sitemaps yet — publish an entry in a routable collection to see it here.</div>
            </xsl:when>
            <xsl:otherwise>
                <table>
                    <thead>
                        <tr>
                            <th>Collection sitemap</th>
                            <th style="width: 14rem;">Last modified</th>
                        </tr>
                    </thead>
                    <tbody>
                        <xsl:for-each select="s:sitemapindex/s:sitemap">
                            <tr>
                                <td class="loc">
                                    <a href="{s:loc}"><xsl:value-of select="s:loc" /></a>
                                </td>
                                <td class="muted">
                                    <xsl:choose>
                                        <xsl:when test="s:lastmod"><xsl:value-of select="s:lastmod" /></xsl:when>
                                        <xsl:otherwise>—</xsl:otherwise>
                                    </xsl:choose>
                                </td>
                            </tr>
                        </xsl:for-each>
                    </tbody>
                </table>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>

    <xsl:template name="urlset">
        <xsl:variable name="collectionComment" select="//comment()[contains(., 'collection:')][1]" />
        <a class="back" href="/sitemap.xml">&#8592; Sitemap index</a>
        <h1 style="margin-top: 0.75rem;">
            <xsl:choose>
                <xsl:when test="$collectionComment">
                    <xsl:variable name="handle" select="substring-before(concat(normalize-space(substring-after($collectionComment, 'collection:')), ' '), ' ')" />
                    <xsl:value-of select="concat(translate(substring($handle, 1, 1), 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'), substring($handle, 2))" />
                </xsl:when>
                <xsl:otherwise>Sitemap</xsl:otherwise>
            </xsl:choose>
        </h1>
        <p class="lead">Public URLs for this collection, kept in sync automatically as entries are published.</p>
        <span class="count-badge">
            <xsl:value-of select="count(s:urlset/s:url)" /> URL<xsl:if test="count(s:urlset/s:url) != 1">s</xsl:if>
        </span>

        <table>
            <thead>
                <tr>
                    <th>URL</th>
                    <th style="width: 6rem;">Priority</th>
                    <th style="width: 8rem;">Changefreq</th>
                    <th style="width: 14rem;">Last modified</th>
                </tr>
            </thead>
            <tbody>
                <xsl:for-each select="s:urlset/s:url">
                    <tr>
                        <td class="loc">
                            <a href="{s:loc}"><xsl:value-of select="s:loc" /></a>
                        </td>
                        <td><span class="pill"><xsl:value-of select="s:priority" /></span></td>
                        <td class="muted"><xsl:value-of select="s:changefreq" /></td>
                        <td class="muted">
                            <xsl:choose>
                                <xsl:when test="s:lastmod"><xsl:value-of select="s:lastmod" /></xsl:when>
                                <xsl:otherwise>—</xsl:otherwise>
                            </xsl:choose>
                        </td>
                    </tr>
                </xsl:for-each>
            </tbody>
        </table>
    </xsl:template>
</xsl:stylesheet>
