<div class="container py-10 content-center clear">

    <h1 class="py-5 text-2xl">
        <a href="/feed?id=<?= $item['feed_id'] ?>"><?= $feed['name'] ?></a>
    </h1>

    <h1 class="text-xl pb-5"><?= $item['name'] ?></h1>

    <div class="media-container">
        <img src="/image?<?= 'item_id='.$item['id'] ?: 'feed_id'.$feed['id'] ?>" class="album-art">
        <audio controls src="/audio?item_id=<?= $item['id'] ?>" class="player"></audio>
    </div>

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

   @media (max-width: 640px) {
       .media-container {
           max-width: 90%;
       }
   }
</style>
<script type="text/javascript">
    (function() {
        const audio = document.querySelector('audio');
        const itemId = <?= $item['id'] ?>;
        const interval = (<?= $this->main->getConf('podsumer', 'playback_interval') ?? 5 ?>) * 1000;
        const rewind = <?= $this->main->getConf('podsumer', 'playback_rewind') ?? 5 ?>;
        let sbSegments = null;
        let sbReady = false;

        async function computeHashPrefix() {
            const resp = await fetch('/audio?item_id=' + itemId, {headers: {Range: 'bytes=0-131071'}});
            const buf = await resp.arrayBuffer();
            const hash = await crypto.subtle.digest('SHA-256', buf);
            return Array.from(new Uint8Array(hash)).map(b => b.toString(16).padStart(2,'0')).join('').substring(0, 6);
        }

        async function checkSponsorblock() {
            const r = await fetch('/get_sponsorblock?item_id=' + itemId);
            if (r.ok) {
                const data = await r.json();
                sbSegments = (data[0]?.segments || []).map(s => s.segment);
                sbReady = true;
            } else if (r.status === 204) {
                sbReady = false;
            }
        }

        async function fetchSponsorblock() {
            try {
                const prefix = await computeHashPrefix();
                const categories = encodeURIComponent(JSON.stringify(['sponsor']));
                const api = 'https://sponsor.ajay.app/api/skipSegments/' + prefix +
                    '?categories=' + categories + '&service=podcast';
                const data = await fetch(api).then(x => x.json());
                sbSegments = (data[0]?.segments || []).map(s => s.segment);

                const params = new URLSearchParams();
                params.append('item_id', itemId);
                params.append('data', JSON.stringify(data));
                fetch('/set_sponsorblock', {method: 'POST', body: params});
            } finally {
                sbReady = true;
            }
        }

        function skipIfNeeded() {
            if (!sbSegments) return;
            const pos = audio.currentTime;
            for (const seg of sbSegments) {
                if (pos >= seg[0] && pos < seg[1]) {
                    audio.currentTime = seg[1];
                    break;
                }
            }
        }

        audio.addEventListener('timeupdate', skipIfNeeded);

        // check for cached sponsorblock data when page loads
        checkSponsorblock();

        audio.addEventListener('play', async () => {
            if (!sbReady) {
                audio.pause();
                await fetchSponsorblock();
                audio.play();
            }
        }, {once: true});

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
    })();
</script>
