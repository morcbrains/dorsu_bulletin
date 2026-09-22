<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:template match="/">
<html>
<head>
    <meta charset="UTF-8" />
    <title>DOrSU Bulletin | Announcement Ledger</title>
    <style>
        body { margin: 0; font-family: "Segoe UI", Tahoma, sans-serif; background: #e8eef8; color: #14233d; }
        .page { max-width: 1180px; margin: 28px auto; padding: 0 16px 40px; }
        .masthead { display: grid; grid-template-columns: 1.4fr .6fr; gap: 0; border-radius: 20px; overflow: hidden; box-shadow: 0 16px 34px rgba(0,43,127,.14); }
        .masthead-main { background: #002b7f; color: #fff; padding: 28px 30px; }
        .masthead-side { background: #f6c400; color: #002b7f; padding: 28px 24px; display: flex; flex-direction: column; justify-content: center; }
        .masthead-main h1 { margin: 0 0 8px; font-size: 30px; }
        .masthead-main p { margin: 0; line-height: 1.6; opacity: .95; }
        .masthead-side strong { display: block; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; }
        .masthead-side span { font-size: 22px; font-weight: 800; margin-top: 6px; }
        .content { margin-top: 22px; background: #fff; border: 1px solid #d5e0f2; border-radius: 20px; overflow: hidden; }
        .content-head { padding: 18px 24px; background: #f8fbff; border-bottom: 1px solid #d5e0f2; font-weight: 700; color: #002b7f; }
        .ledger { width: 100%; border-collapse: collapse; }
        .ledger th { background: #123b88; color: #fff; text-align: left; padding: 14px 16px; font-size: 12px; letter-spacing: .06em; text-transform: uppercase; }
        .ledger td { padding: 16px; border-bottom: 1px solid #e6edf8; vertical-align: top; font-size: 14px; line-height: 1.55; }
        .ledger tr:nth-child(even) td { background: #fbfdff; }
        .ledger tr:hover td { background: #fff9df; }
        .title-cell strong { display: block; color: #002b7f; margin-bottom: 4px; }
        .title-cell small { color: #5f6d84; }
        .chip { display: inline-block; padding: 5px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .urgent { background: #ffe2e2; color: #9f1d1d; }
        .normal { background: #e7efff; color: #002b7f; }
        .published { background: #ddf8e6; color: #17653a; }
        .draft { background: #fff4cc; color: #7a5d00; }
        .footer { margin-top: 16px; color: #607089; font-size: 13px; padding: 0 6px; }
    </style>
</head>
<body>
    <div class="page">
        <div class="masthead">
            <div class="masthead-main">
                <h1>Official Announcement Ledger</h1>
                <p>Structured announcement records exported from the DOrSU Bulletin database and transformed through XSLT.</p>
            </div>
            <div class="masthead-side">
                <strong>Generated</strong>
                <span><xsl:value-of select="/announcements/@generatedAt" /></span>
            </div>
        </div>

        <div class="content">
            <div class="content-head">Announcement Records</div>
            <table class="ledger">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Notice</th>
                        <th>Category</th>
                        <th>Audience</th>
                        <th>Urgency</th>
                        <th>Status</th>
                        <th>Posted</th>
                        <th>Office</th>
                    </tr>
                </thead>
                <tbody>
                    <xsl:for-each select="announcements/announcement">
                        <tr>
                            <td><xsl:value-of select="id" /></td>
                            <td class="title-cell">
                                <strong><xsl:value-of select="title" /></strong>
                                <small><xsl:value-of select="description" /></small>
                            </td>
                            <td><xsl:value-of select="category" /></td>
                            <td><xsl:value-of select="target_audience" /></td>
                            <td>
                                <span>
                                    <xsl:attribute name="class">
                                        <xsl:text>chip </xsl:text>
                                        <xsl:value-of select="urgency" />
                                    </xsl:attribute>
                                    <xsl:value-of select="urgency" />
                                </span>
                            </td>
                            <td>
                                <span>
                                    <xsl:attribute name="class">
                                        <xsl:text>chip </xsl:text>
                                        <xsl:value-of select="status" />
                                    </xsl:attribute>
                                    <xsl:value-of select="status" />
                                </span>
                            </td>
                            <td><xsl:value-of select="created_at" /></td>
                            <td><xsl:value-of select="posted_by" /></td>
                        </tr>
                    </xsl:for-each>
                </tbody>
            </table>
        </div>

        <div class="footer">DOrSU Bulletin Reporting Module | XSLT HTML Output</div>
    </div>
</body>
</html>
</xsl:template>

</xsl:stylesheet>
