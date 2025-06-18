<div class="container py-10">
    <h1 class="text-2xl py-4">Background Jobs</h1>

    <? if (!empty($running_jobs)): ?>
    <div class="py-8">
        <h2 class="text-xl py-4">Running Jobs (<?= count($running_jobs) ?>)</h2>
        <div class="py-4">
            <table class="w-full">
                <thead>
                    <tr class="text-left">
                        <th class="py-3 pl-4 text-lg font-bold">Type</th>
                        <th class="py-3 pl-4 text-lg font-bold">Target</th>
                        <th class="py-3 pl-4 text-lg font-bold">Status</th>
                        <th class="py-3 pl-4 text-lg font-bold">Started</th>
                        <th class="py-3 pl-4 text-lg font-bold">Duration</th>
                        <th class="py-3 pl-4 text-lg font-bold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <? foreach ($running_jobs as $index => $job): ?>
                    <tr class="<?= $index % 2 === 1 ? 'bg-neutral-800' : '' ?>">
                        <td class="py-3 pl-4 text-base">
                            <?= ucfirst(str_replace('_', ' ', $job['type'])) ?>
                        </td>
                        <td class="py-3 pl-4 text-base">
                            <? if ($job['feed_name']): ?>
                                <?= htmlspecialchars(substr($job['feed_name'], 0, 30)) ?>
                            <? elseif ($job['item_name']): ?>
                                <?= htmlspecialchars(substr($job['item_name'], 0, 30)) ?>
                            <? else: ?>
                                <span class="text-neutral-500">All feeds</span>
                            <? endif ?>
                        </td>
                        <td class="py-3 pl-4 text-base">
                            <span class="<?= 
                                $job['status'] === 'running' ? 'text-yellow-400' : 'text-blue-400'
                            ?>">
                                <?= ucfirst($job['status']) ?>
                            </span>
                            <? if ($job['pid']): ?>
                                <span class="text-neutral-500 text-sm"> (PID: <?= $job['pid'] ?>)</span>
                            <? endif ?>
                        </td>
                        <td class="py-3 pl-4 text-sm text-neutral-400">
                            <? if ($job['started_at']): ?>
                                <?= date('m/d H:i', strtotime($job['started_at'])) ?>
                            <? else: ?>
                                <span class="text-neutral-500">-</span>
                            <? endif ?>
                        </td>
                        <td class="py-3 pl-4 text-sm text-neutral-400">
                            <? if ($job['started_at']): ?>
                                <?= gmdate('H:i:s', time() - strtotime($job['started_at'])) ?>
                            <? else: ?>
                                <span class="text-neutral-500">-</span>
                            <? endif ?>
                        </td>
                        <td class="py-3 pl-4 text-base">
                            <button onclick="cancelJob(<?= $job['id'] ?>)" class="text-red-400 hover:text-red-300 underline">Cancel</button>
                        </td>
                    </tr>
                    <? endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
    <? endif ?>

    <div class="py-8">
        <h2 class="text-xl py-4">Job Statistics</h2>
        <div class="py-4">
            <span class="text-2xl"><?= $job_stats['total_jobs'] ?></span>
            <span class="text-neutral-400"> Total Jobs</span>
            &nbsp;|&nbsp;
            <span class="text-2xl text-yellow-400"><?= $job_stats['running_jobs'] + $job_stats['queued_jobs'] ?></span>
            <span class="text-neutral-400"> Active Jobs</span>
            &nbsp;|&nbsp;
            <span class="text-2xl text-green-400"><?= $job_stats['completed_jobs'] ?></span>
            <span class="text-neutral-400"> Completed</span>
            &nbsp;|&nbsp;
            <span class="text-2xl text-red-400"><?= $job_stats['failed_jobs'] ?></span>
            <span class="text-neutral-400"> Failed</span>
            <? if ($job_stats['total_openai_cost'] > 0): ?>
            &nbsp;|&nbsp;
            <span class="text-xl text-green-300">$<?= number_format($job_stats['total_openai_cost'], 4) ?></span>
            <span class="text-neutral-400"> Total OpenAI Cost</span>
            <? endif ?>
        </div>
    </div>

    <div class="py-8">
        <h2 class="text-xl py-4">Recent Jobs</h2>
        <? if (empty($jobs)): ?>
        <p class="text-neutral-400 py-4">No jobs found.</p>
        <? else: ?>
        <div class="py-4">
            <table class="w-full">
                <thead>
                    <tr class="text-left">
                        <th class="py-3 pl-4 text-lg font-bold">Type</th>
                        <th class="py-3 pl-4 text-lg font-bold">Target</th>
                        <th class="py-3 pl-4 text-lg font-bold">Status</th>
                        <th class="py-3 pl-4 text-lg font-bold">Created</th>
                        <th class="py-3 pl-4 text-lg font-bold">Duration</th>
                        <th class="py-3 pl-4 text-lg font-bold">Cost</th>
                    </tr>
                </thead>
                <tbody>
                    <? foreach ($jobs as $index => $job): ?>
                    <tr class="<?= $index % 2 === 1 ? 'bg-neutral-800' : '' ?>">
                        <td class="py-3 pl-4 text-base">
                            <?= ucfirst(str_replace('_', ' ', $job['type'])) ?>
                        </td>
                        <td class="py-3 pl-4 text-base">
                            <? if ($job['feed_name']): ?>
                                <?= htmlspecialchars(substr($job['feed_name'], 0, 30)) ?>
                            <? elseif ($job['item_name']): ?>
                                <?= htmlspecialchars(substr($job['item_name'], 0, 30)) ?>
                            <? else: ?>
                                <span class="text-neutral-500">All feeds</span>
                            <? endif ?>
                        </td>
                        <td class="py-3 pl-4 text-base">
                            <span class="<?= 
                                $job['status'] === 'completed' ? 'text-green-400' : 
                                ($job['status'] === 'running' ? 'text-yellow-400' : 
                                ($job['status'] === 'failed' ? 'text-red-400' : 
                                ($job['status'] === 'cancelled' ? 'text-neutral-500' : 'text-blue-400')))
                            ?>">
                                <?= ucfirst($job['status']) ?>
                            </span>
                        </td>
                        <td class="py-3 pl-4 text-sm text-neutral-400">
                            <?= date('m/d H:i', strtotime($job['created_at'])) ?>
                        </td>
                        <td class="py-3 pl-4 text-sm text-neutral-400">
                            <? if ($job['started_at'] && $job['finished_at']): ?>
                                <?= gmdate('H:i:s', strtotime($job['finished_at']) - strtotime($job['started_at'])) ?>
                            <? elseif ($job['started_at']): ?>
                                <?= gmdate('H:i:s', time() - strtotime($job['started_at'])) ?>
                            <? else: ?>
                                <span class="text-neutral-500">-</span>
                            <? endif ?>
                        </td>
                        <td class="py-3 pl-4 text-sm text-neutral-400">
                            <? if ($job['openai_cost'] > 0): ?>
                                $<?= number_format($job['openai_cost'], 4) ?>
                            <? else: ?>
                                <span class="text-neutral-500">-</span>
                            <? endif ?>
                        </td>
                    </tr>
                    <? if ($job['error'] || $job['log']): ?>
                    <tr class="<?= $index % 2 === 1 ? 'bg-neutral-800' : '' ?>">
                        <td colspan="6" class="py-3 pl-4">
                            <? if ($job['error']): ?>
                            <div class="text-red-400 py-1 text-sm">
                                <strong>Error:</strong> <?= htmlspecialchars($job['error']) ?>
                            </div>
                            <? endif ?>
                            <? if ($job['log']): ?>
                            <div class="py-1">
                                <button onclick="toggleLog(<?= $job['id'] ?>)" class="text-blue-400 hover:text-blue-300 underline text-sm">
                                    <span id="log-toggle-<?= $job['id'] ?>">▶ Show Log</span>
                                </button>
                                <div id="log-content-<?= $job['id'] ?>" class="hidden py-2">
                                    <div class="text-neutral-400 font-mono text-xs whitespace-pre-wrap"><?= htmlspecialchars($job['log']) ?></div>
                                </div>
                            </div>
                            <? endif ?>
                        </td>
                    </tr>
                    <? endif ?>
                    <? endforeach ?>
                </tbody>
            </table>
        </div>
        <? endif ?>
    </div>
</div>

<script>
function cancelJob(jobId) {
    if (!confirm('Are you sure you want to cancel this job?')) {
        return;
    }
    
    fetch('/cancel_job', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ job_id: jobId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error cancelling job: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        alert('Error cancelling job: ' + err.message);
        console.error('Error:', err);
    });
}

function toggleLog(jobId) {
    const content = document.getElementById('log-content-' + jobId);
    const toggle = document.getElementById('log-toggle-' + jobId);
    
    if (content.classList.contains('hidden')) {
        content.classList.remove('hidden');
        toggle.textContent = '▼ Hide Log';
    } else {
        content.classList.add('hidden');
        toggle.textContent = '▶ Show Log';
    }
}
</script> 