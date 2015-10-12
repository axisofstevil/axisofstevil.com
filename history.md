---
layout: page
title: History
permalink: /history/
sitemap:
    priority: 0.9
    changefreq: 'weekly'
---

The following is a true historical accounting of the organization as recorded in the tomes and great history books of the brotherhood, stored in the great vault of the Vatican city, and transcribed for you here.

<dl class="timeline">
    {% assign history = site.history | sort: 'year' %}
    {% for event in history reversed %}
    <dt>{{ event.year | remove:'-' }}{% if event.year < 0 %} <small>B.C.</small>{% endif %}</dt>
    <dd>{{ event.content }}</dd>
    {% endfor %}
</dl>
