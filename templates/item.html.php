<div class="container py-10 content-center clear">

    <h1 class="py-5 text-2xl">
        <a href="/feed?id=<?= $item['feed_id'] ?>"><?= $feed['name'] ?></a>
    </h1>

    <h1 class="text-xl pb-5"><?= $item['name'] ?></h1>

    <div class="media-container">
        <img src="/image?<?= 'item_id='.$item['id'] ?: 'feed_id'.$feed['id'] ?>" class="album-art">
        <audio autoplay controls src="/audio?item_id=<?= $item['id'] ?>" class="player"></audio>
    </div>

    <?php
        $sponsorOn = filter_var($this->main->getConf('podsumer', 'sponsorblock_enabled'), FILTER_VALIDATE_BOOLEAN);
    ?>

    <!-- Sponsor break timestamps will be rendered here -->
    <?php if ($sponsorOn): ?>
    <div class="alpha-notice">SponsorBlock skipping is in alpha.</div>
    <?php endif; ?>
    <div id="sponsor-breaks" class="sponsor-breaks"></div>

    <div id="item-desc">
        <?= $item['description'] ?>
    </div>

</div>

<style type="text/css">
   #item-desc {
       max-width: 65ch;
       margin: 0 auto;
       line-height: 1.6;
       font-size: 1.05rem;
   }
   #item-desc a {
       color: rgb(253, 230, 138);
       text-decoration: underline;
   }
   #item-desc p {
       margin-bottom: 1em;
   }

   .media-container {
       max-width: 380px;
       margin: 0 auto 2rem;
       border-radius: 8px;
       box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
       overflow: hidden; /* ensure rounded corners apply to children */
   }

   .album-art {
       display: block;
       width: 100%;
       height: auto;
   }

   .player {
       width: 100%;
       margin: 0;
       border: none;
       background-color: #1f2937; /* neutral-800 */
   }

   /* Darker controls */
   .player::-webkit-media-controls-panel {
       background-color: #1f2937;
       color: rgb(253, 230, 138);
   }

   .sponsor-breaks {
       max-width: 380px;
       margin: 0.5rem auto 1.5rem;
       text-align: center;
       font-size: 0.9rem;
       color: rgb(253, 230, 138);
   }

   .sponsor-breaks a {
       color: rgb(253, 230, 138);
       text-decoration: underline;
       margin: 0 4px;
   }
   .sponsor-breaks a:hover {
       opacity: 0.8;
   }

   .alpha-notice {
       text-align: center;
       font-size: 0.8rem;
       color: rgb(253, 230, 138);
       margin-bottom: 0.25rem;
   }

   @media (max-width: 640px) {
       .media-container {
           max-width: 90%;
       }
   }
</style>
<script type="text/javascript">
    (function() {
        const sponsorEnabled = <?= $sponsorOn ? 'true' : 'false' ?>;

        const audio = document.querySelector('audio');
        const itemId = <?= $item['id'] ?>;
        const interval = (<?= $this->main->getConf('podsumer', 'playback_interval') ?? 5 ?>) * 1000;
        const rewind = <?= $this->main->getConf('podsumer', 'playback_rewind') ?? 5 ?>;

        fetch('/get_playback?item_id=' + itemId)
            .then(r => r.json())
            .then(d => {
                if (d.position > 0) {
                    audio.currentTime = Math.max(0, d.position - rewind);
                }
            });

        setInterval(() => {
            if (!audio.paused) {
                const params = new URLSearchParams();
                params.append('item_id', itemId);
                params.append('position', Math.floor(audio.currentTime));
                fetch('/set_playback', {method: 'POST', body: params});
            }
        }, interval);

        // Convert bare URLs inside #item-desc to clickable links that open in new tab
        (function linkifyDesc() {
            const container = document.getElementById('item-desc');
            if (!container) return;

            const urlRegex = /https?:\/\/[^\s]+/g;

            function linkifyNode(node) {
                // Process only text nodes
                if (node.nodeType === Node.TEXT_NODE) {
                    const text = node.textContent;
                    if (!text) return;
                    if (urlRegex.test(text)) {
                        const span = document.createElement('span');
                        span.innerHTML = text.replace(urlRegex, url => `<a href="${url}" target="_blank" rel="noopener noreferrer">${url}</a>`);
                        node.replaceWith(...span.childNodes);
                    }
                } else if (node.nodeType === Node.ELEMENT_NODE && node.tagName !== 'A') {
                    // Recurse into child nodes but skip existing anchors
                    Array.from(node.childNodes).forEach(linkifyNode);
                }
            }

            Array.from(container.childNodes).forEach(linkifyNode);
        })();

        // Ensure any existing links open in a new window/tab
        document.querySelectorAll('#item-desc a').forEach(link => {
            link.setAttribute('target', '_blank');
            link.setAttribute('rel', 'noopener noreferrer');
        });

        /* ------------------------------------------------------------------
         * SponsorBlock integration – fetch segments and auto-skip them.
         * ------------------------------------------------------------------ */
        if (sponsorEnabled) {
            fetch('/sponsor_segments?item_id=' + itemId)
                .then(r => r.json())
                .then(data => {
                    const { videoId, segments } = Array.isArray(data) ? { videoId: null, segments: data } : data;

                    if (!Array.isArray(segments) || segments.length === 0) return;

                    // Prepare enriched data containing start, end, category
                    const enriched = segments
                        .map(s =>
                            Array.isArray(s.segment)
                                ? {
                                      start: Number(s.segment[0]),
                                      end:   Number(s.segment[1]),
                                      category: s.category || ''
                                  }
                                : null
                        )
                        .filter(Boolean)
                        .sort((a, b) => a.start - b.start); // ensure chronological order

                    // Extract ranges ([start,end]) for auto-skip logic
                    const ranges = enriched.map(e => [e.start, e.end]);

                    /* ------------------------------------------------------
                     * 1) Render sponsor break list with clickable end times
                     * ------------------------------------------------------ */
                    const container = document.getElementById('sponsor-breaks');
                    if (container) {
                        function fmt(sec) {
                            const m = Math.floor(sec / 60);
                            const s = Math.floor(sec % 60).toString().padStart(2, '0');
                            return `${m}:${s}`;
                        }

                        const label = document.createElement('span');
                        label.textContent = videoId ? `Sponsor breaks (video ${videoId}):` : 'Sponsor breaks:';
                        container.appendChild(label);

                        enriched.forEach((seg, idx) => {
                            const { start, end, category } = seg;
                            
                            // separator bullet except first
                            if (idx > 0) {
                                const sep = document.createTextNode(' • ');
                                container.appendChild(sep);
                            }

                            const link = document.createElement('a');
                            link.href = `#t=${Math.floor(end)}`; // for bookmarking, optional
                            link.textContent = `${fmt(start)}–${fmt(end)}${category ? ' (' + category + ')' : ''}`;
                            link.dataset.time = end.toString();

                            link.addEventListener('click', ev => {
                                ev.preventDefault();
                                // Seek safely to just after the sponsor break ends
                                const target = Number(link.dataset.time) + 0.05;
                                const max = (Number.isFinite(audio.duration) && audio.duration > 0) ? audio.duration - 0.01 : target;
                                audio.currentTime = Math.min(target, max);
                                audio.play();
                            });

                            container.appendChild(link);
                        });
                    }

                    let currentRangeIdx = -1;
                    let skipTarget = null; // holds the time we are currently trying to seek to

                    function safeJump(endTime) {
                        const buffer = 0.05;
                        const desired = endTime + buffer;
                        const max = (Number.isFinite(audio.duration) && audio.duration > 0) ? audio.duration - 0.01 : desired;
                        skipTarget = Math.min(desired, max);
                        audio.currentTime = skipTarget;
                    }

                    audio.addEventListener('timeupdate', () => {
                        const t = audio.currentTime;

                        // If a skip is in progress, wait until we are very close to the target before clearing it
                        if (skipTarget !== null) {
                            if (t >= skipTarget - 0.02) {
                                skipTarget = null; // reached target (or close enough)
                            } else {
                                return; // still waiting for the player to seek; do nothing else
                            }
                        }

                        // Check if we are currently inside a sponsor range
                        for (let i = 0; i < ranges.length; i++) {
                            const [start, end] = ranges[i];
                            if (t >= start && t < end) {
                                currentRangeIdx = i;
                                safeJump(end);
                                break;
                            }
                        }
                    });
                })
                .catch(() => {/* ignore network errors */});
        }
    })();
</script>
