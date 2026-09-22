<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:template match="/">
<html>
<head>
    <meta charset="UTF-8" />
    <title>DOrSU Bulletin | Campus Summary Report</title>
    <style>
        body { margin: 0; font-family: "Segoe UI", Tahoma, sans-serif; background: linear-gradient(180deg, #dfe8f8 0%, #f7f9fc 220px); color: #14233d; }
        .layout { max-width: 1240px; margin: 0 auto; padding: 28px 18px 42px; }
        .topbar { background: #002b7f; color: #fff; border-radius: 22px; padding: 26px 28px; position: relative; overflow: hidden; }
        .topbar:after { content: ""; position: absolute; right: -40px; top: -30px; width: 180px; height: 180px; border-radius: 50%; background: rgba(246,196,0,.18); }
        .topbar h1 { margin: 0 0 6px; font-size: 31px; position: relative; z-index: 1; }
        .topbar p { margin: 0; line-height: 1.6; max-width: 760px; position: relative; z-index: 1; }
        .stamp { margin-top: 12px; display: inline-block; background: #f6c400; color: #002b7f; padding: 8px 12px; border-radius: 999px; font-size: 12px; font-weight: 800; position: relative; z-index: 1; }
        .summary-grid { margin-top: 18px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
        .summary-card { background: #fff; border: 1px solid #d5e0f2; border-top: 5px solid #f6c400; border-radius: 16px; padding: 16px 18px; box-shadow: 0 8px 20px rgba(0,43,127,.06); }
        .summary-card strong { display: block; font-size: 28px; color: #002b7f; }
        .summary-card span { color: #607089; font-size: 13px; }
        .section { margin-top: 22px; background: #fff; border: 1px solid #d5e0f2; border-radius: 18px; overflow: hidden; }
        .section-title { padding: 16px 20px; background: #f8fbff; border-bottom: 1px solid #d5e0f2; color: #002b7f; font-size: 18px; font-weight: 800; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #123b88; color: #fff; text-align: left; padding: 12px 14px; font-size: 12px; text-transform: uppercase; letter-spacing: .05em; }
        td { padding: 12px 14px; border-bottom: 1px solid #e7edf8; vertical-align: top; font-size: 14px; line-height: 1.5; }
        tr:nth-child(even) td { background: #fbfdff; }
        .role-pill { display: inline-block; background: #fff4cc; color: #002b7f; border-radius: 999px; padding: 5px 10px; font-size: 11px; font-weight: 800; }
        .footer { margin-top: 18px; color: #607089; font-size: 13px; }
        @media (max-width: 900px) { .summary-grid { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>
    <div class="layout">
        <div class="topbar">
            <h1><xsl:value-of select="dorsu_bulletin/system_information/system_name" /></h1>
            <p>
                <xsl:value-of select="dorsu_bulletin/system_information/university" /> —
                <xsl:value-of select="dorsu_bulletin/system_information/description" />
            </p>
            <div class="stamp">Generated at: <xsl:value-of select="/dorsu_bulletin/@generatedAt" /></div>
        </div>

        <div class="summary-grid">
            <div class="summary-card">
                <strong><xsl:value-of select="count(dorsu_bulletin/approved_accounts/account)" /></strong>
                <span>Approved Accounts</span>
            </div>
            <div class="summary-card">
                <strong><xsl:value-of select="count(dorsu_bulletin/announcements/announcement)" /></strong>
                <span>Announcements</span>
            </div>
            <div class="summary-card">
                <strong><xsl:value-of select="count(dorsu_bulletin/events/event)" /></strong>
                <span>University Events</span>
            </div>
            <div class="summary-card">
                <strong><xsl:value-of select="count(dorsu_bulletin/notifications/notification)" /></strong>
                <span>Notifications</span>
            </div>
        </div>

        <div class="section">
            <div class="section-title">01 | Role Overview (Live Database Counts)</div>
            <table>
                <tr><th>Role</th><th>Approved Accounts</th><th>Responsibility</th></tr>
                <xsl:for-each select="dorsu_bulletin/role_overview/role_record">
                    <tr>
                        <td><span class="role-pill"><xsl:value-of select="role" /></span></td>
                        <td><xsl:value-of select="approved_count" /></td>
                        <td><xsl:value-of select="responsibility" /></td>
                    </tr>
                </xsl:for-each>
            </table>
        </div>

        <div class="section">
            <div class="section-title">02 | Approved System Accounts</div>
            <table>
                <tr><th>Name</th><th>Email</th><th>Role</th><th>Unit</th><th>Status</th></tr>
                <xsl:for-each select="dorsu_bulletin/approved_accounts/account">
                    <tr>
                        <td><xsl:value-of select="full_name" /></td>
                        <td><xsl:value-of select="email" /></td>
                        <td><xsl:value-of select="role" /></td>
                        <td><xsl:value-of select="unit" /></td>
                        <td><xsl:value-of select="status" /></td>
                    </tr>
                </xsl:for-each>
            </table>
        </div>

        <div class="section">
            <div class="section-title">03 | Announcements</div>
            <table>
                <tr><th>Title</th><th>Category</th><th>Audience</th><th>Urgency</th><th>Status</th><th>Posted By</th></tr>
                <xsl:for-each select="dorsu_bulletin/announcements/announcement">
                    <tr>
                        <td><xsl:value-of select="title" /></td>
                        <td><xsl:value-of select="category" /></td>
                        <td><xsl:value-of select="target_audience" /></td>
                        <td><xsl:value-of select="urgency" /></td>
                        <td><xsl:value-of select="status" /></td>
                        <td><xsl:value-of select="posted_by" /></td>
                    </tr>
                </xsl:for-each>
            </table>
        </div>

        <div class="section">
            <div class="section-title">04 | University Events</div>
            <table>
                <tr><th>Event</th><th>Date</th><th>Venue</th><th>Organizer</th><th>Status</th></tr>
                <xsl:for-each select="dorsu_bulletin/events/event">
                    <tr>
                        <td><xsl:value-of select="event_title" /></td>
                        <td><xsl:value-of select="event_date" /></td>
                        <td><xsl:value-of select="venue" /></td>
                        <td><xsl:value-of select="organizer" /></td>
                        <td><xsl:value-of select="status" /></td>
                    </tr>
                </xsl:for-each>
            </table>
        </div>

        <div class="section">
            <div class="section-title">05 | Private Messages</div>
            <table>
                <tr><th>Sender</th><th>Receiver</th><th>Message</th><th>Date</th></tr>
                <xsl:for-each select="dorsu_bulletin/messages/message_record">
                    <tr>
                        <td><xsl:value-of select="sender" /></td>
                        <td><xsl:value-of select="receiver" /></td>
                        <td><xsl:value-of select="message" /></td>
                        <td><xsl:value-of select="created_at" /></td>
                    </tr>
                </xsl:for-each>
            </table>
        </div>

        <div class="section">
            <div class="section-title">06 | Notifications</div>
            <table>
                <tr><th>Type</th><th>Message</th><th>Receiver</th><th>Status</th></tr>
                <xsl:for-each select="dorsu_bulletin/notifications/notification">
                    <tr>
                        <td><xsl:value-of select="type" /></td>
                        <td><xsl:value-of select="message" /></td>
                        <td><xsl:value-of select="receiver" /></td>
                        <td><xsl:value-of select="status" /></td>
                    </tr>
                </xsl:for-each>
            </table>
        </div>

        <div class="footer">DOrSU Bulletin Campus Summary | XSLT Transformation Output</div>
    </div>
</body>
</html>
</xsl:template>

</xsl:stylesheet>
