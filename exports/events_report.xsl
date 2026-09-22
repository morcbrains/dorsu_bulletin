<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:template match="/">
<html>
<head>
    <meta charset="UTF-8" />
    <title>DOrSU Bulletin | Events Timeline</title>
    <style>
        body { margin: 0; font-family: Georgia, "Times New Roman", serif; background: #f3f6fb; color: #15243f; }
        .wrap { max-width: 980px; margin: 30px auto; padding: 0 18px 40px; }
        .banner { background: #002b7f; color: #fff; padding: 26px 28px; border-left: 12px solid #f6c400; border-radius: 0 18px 18px 0; }
        .banner h1 { margin: 0 0 8px; font-size: 32px; }
        .banner p { margin: 0; font-family: "Segoe UI", Tahoma, sans-serif; line-height: 1.6; opacity: .95; }
        .meta { margin-top: 14px; font-family: "Segoe UI", Tahoma, sans-serif; font-size: 13px; color: #fff7cc; }
        .timeline { margin-top: 24px; display: grid; gap: 18px; }
        .event-card { display: grid; grid-template-columns: 150px 1fr; background: #fff; border: 1px solid #d7e2f2; border-radius: 18px; overflow: hidden; box-shadow: 0 10px 24px rgba(0,43,127,.08); }
        .date-box { background: #f6c400; color: #002b7f; padding: 22px 16px; text-align: center; }
        .date-box .day { display: block; font-size: 28px; font-weight: 800; line-height: 1; }
        .date-box .time { display: block; margin-top: 10px; font-family: "Segoe UI", Tahoma, sans-serif; font-size: 12px; font-weight: 700; }
        .details { padding: 20px 22px; font-family: "Segoe UI", Tahoma, sans-serif; }
        .details h2 { margin: 0 0 8px; font-size: 22px; color: #002b7f; }
        .details p { margin: 0 0 12px; line-height: 1.6; color: #4d5d78; }
        .facts { display: flex; flex-wrap: wrap; gap: 10px; }
        .fact { background: #eef4ff; color: #002b7f; border: 1px solid #d2def4; border-radius: 999px; padding: 6px 12px; font-size: 12px; font-weight: 700; }
        .status-published { background: #ddf8e6; color: #17653a; border-color: #bfe8cc; }
        .footer { margin-top: 18px; font-family: "Segoe UI", Tahoma, sans-serif; color: #66758d; font-size: 13px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="banner">
            <h1>University Events Timeline</h1>
            <p>Event records rendered as a timeline-style XSLT report for easier campus activity review.</p>
            <div class="meta">Generated at: <xsl:value-of select="/university_events/@generatedAt" /></div>
        </div>

        <div class="timeline">
            <xsl:for-each select="university_events/event">
                <div class="event-card">
                    <div class="date-box">
                        <span class="day"><xsl:value-of select="event_date" /></span>
                        <span class="time">
                            <xsl:value-of select="start_time" />
                            <xsl:text> - </xsl:text>
                            <xsl:value-of select="end_time" />
                        </span>
                    </div>
                    <div class="details">
                        <h2><xsl:value-of select="event_title" /></h2>
                        <p><xsl:value-of select="event_description" /></p>
                        <div class="facts">
                            <span class="fact">Category: <xsl:value-of select="event_category" /></span>
                            <span class="fact">Venue: <xsl:value-of select="venue" /></span>
                            <span class="fact">Organizer: <xsl:value-of select="organizer" /></span>
                            <span class="fact">Audience: <xsl:value-of select="target_audience" /></span>
                            <span class="fact">Created by: <xsl:value-of select="created_by" /></span>
                            <span>
                                <xsl:attribute name="class">
                                    <xsl:text>fact </xsl:text>
                                    <xsl:if test="status = 'published'">status-published</xsl:if>
                                </xsl:attribute>
                                Status: <xsl:value-of select="status" />
                            </span>
                        </div>
                    </div>
                </div>
            </xsl:for-each>
        </div>

        <div class="footer">DOrSU Bulletin Events Report | XSLT Transformation Output</div>
    </div>
</body>
</html>
</xsl:template>

</xsl:stylesheet>
