// Simple Notification System
// Works immediately - no complicated setup!

class SimpleNotifications {
    constructor() {
        this.swRegistration = null;
        this.isEnabled = false;
        this.init();
    }
    
    // Initialize
    async init() {
        // Check browser support
        if (!('serviceWorker' in navigator) || !('Notification' in window)) {
            console.log('Notifications not supported');
            return;
        }
        
        try {
            // Register service worker
            this.swRegistration = await navigator.serviceWorker.register('sw.js');
            console.log('Service Worker registered!');
            
            // Check permission
            if (Notification.permission === 'granted') {
                this.isEnabled = true;
                this.startScheduledNotifications();
            } else if (Notification.permission === 'default') {
                // Show permission prompt after 3 seconds
                setTimeout(() => this.showPermissionPrompt(), 3000);
            }
        } catch (error) {
            console.error('Service Worker failed:', error);
        }
    }
    
    // Show permission prompt
    showPermissionPrompt() {
        const prompt = document.createElement('div');
        prompt.id = 'notification-prompt';
        prompt.innerHTML = `
            <div style="position:fixed;bottom:20px;right:20px;background:white;padding:1.5rem;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.25);max-width:350px;z-index:10000;border:2px solid var(--glacier);animation:slideIn 0.3s ease">
                <div style="display:flex;align-items:start;gap:1rem">
                    <div style="font-size:2.5rem">🔔</div>
                    <div style="flex:1">
                        <h3 style="margin:0 0 0.5rem;font-size:1.125rem;color:var(--text-primary);font-weight:700">Enable Notifications?</h3>
                        <p style="margin:0 0 1rem;font-size:0.875rem;color:var(--text-secondary);line-height:1.4">
                            Get reminders for tasks, streaks, and achievements!
                        </p>
                        <div style="display:flex;gap:0.5rem">
                            <button onclick="notifications.enable()" style="flex:1;padding:0.75rem;background:var(--primary-gradient);color:white;border:none;border-radius:var(--radius-md);font-weight:700;cursor:pointer;font-size:0.9375rem">
                                Enable
                            </button>
                            <button onclick="notifications.dismiss()" style="padding:0.75rem 1.25rem;background:var(--bg-tertiary);border:none;border-radius:var(--radius-md);cursor:pointer;font-weight:600;color:var(--text-secondary)">
                                Later
                            </button>
                        </div>
                    </div>
                    <button onclick="notifications.dismiss()" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--text-tertiary);padding:0;line-height:1">&times;</button>
                </div>
            </div>
        `;
        document.body.appendChild(prompt);
    }
    
    // Enable notifications
    async enable() {
        this.dismiss();
        
        const permission = await Notification.requestPermission();
        
        if (permission === 'granted') {
            console.log('Notifications enabled!');
            this.isEnabled = true;
            
            // Show success notification
            this.show('Notifications Enabled!', {
                body: 'You\'ll now receive reminders and updates.',
                icon: 'icon.png'
            });
            
            // Start scheduled notifications
            this.startScheduledNotifications();
        }
    }
    
    // Dismiss prompt
    dismiss() {
        const prompt = document.getElementById('notification-prompt');
        if (prompt) prompt.remove();
    }
    
    // Show notification
    show(title, options = {}) {
        if (!this.isEnabled || !this.swRegistration) {
            console.log('Notifications not enabled');
            return;
        }
        
        this.swRegistration.showNotification(title, {
            body: options.body || '',
            icon: options.icon || 'icon.png',
            badge: 'badge.png',
            vibrate: [200, 100, 200],
            tag: options.tag || 'notification',
            requireInteraction: options.requireInteraction || false,
            data: { url: options.url || 'dashboard.php' }
        });
    }
    
    // Schedule daily reminder (9 AM)
    scheduleDailyReminder() {
        const now = new Date();
        const scheduledTime = new Date();
        scheduledTime.setHours(9, 0, 0, 0);
        
        // If already past 9 AM, schedule for tomorrow
        if (now > scheduledTime) {
            scheduledTime.setDate(scheduledTime.getDate() + 1);
        }
        
        const msUntilReminder = scheduledTime - now;
        
        setTimeout(() => {
            this.show('Good Morning!', {
                body: 'Time to check your tasks for today!',
                tag: 'daily-reminder',
                url: 'dashboard.php'
            });
            
            // Schedule next day
            this.scheduleDailyReminder();
        }, msUntilReminder);
        
        console.log('Daily reminder scheduled for', scheduledTime.toLocaleString());
    }
    
    // Schedule streak reminder (8 PM)
    scheduleStreakReminder() {
        const now = new Date();
        const scheduledTime = new Date();
        scheduledTime.setHours(20, 0, 0, 0);
        
        if (now > scheduledTime) {
            scheduledTime.setDate(scheduledTime.getDate() + 1);
        }
        
        const msUntilReminder = scheduledTime - now;
        
        setTimeout(() => {
            // Check if user completed any tasks today
            this.checkTodayProgress();
            
            // Schedule next day
            this.scheduleStreakReminder();
        }, msUntilReminder);
        
        console.log('Streak reminder scheduled for', scheduledTime.toLocaleString());
    }
    
    // Check today's progress
    async checkTodayProgress() {
        try {
            const response = await fetch('api_tasks.php?action=check_today_progress');
            const data = await response.json();
            
            // If no tasks completed, remind user
            if (data.completed === 0 && data.total > 0) {
                this.show('Don\'t Break Your Streak!', {
                    body: `You have ${data.total} task${data.total > 1 ? 's' : ''} pending today!`,
                    tag: 'streak-reminder',
                    requireInteraction: true,
                    url: 'dashboard.php'
                });
            }
        } catch (error) {
            console.error('Failed to check progress:', error);
        }
    }
    
    // Start all scheduled notifications
    startScheduledNotifications() {
        this.scheduleDailyReminder();
        this.scheduleStreakReminder();
        console.log('Scheduled notifications started!');
    }
    
    // Notify badge unlock
    notifyBadgeUnlock(badgeName, xp) {
        this.show('Badge Unlocked!', {
            body: `You earned "${badgeName}"! +${xp} XP`,
            tag: 'badge-unlock',
            requireInteraction: true,
            url: 'gamification.php'
        });
    }
    
    // Notify level up
    notifyLevelUp(level) {
        this.show('Level Up!', {
            body: `Congratulations! You reached Level ${level}!`,
            tag: 'level-up',
            requireInteraction: true,
            url: 'gamification.php'
        });
    }
    
    // Notify task completion
    notifyTaskComplete(taskTitle) {
        this.show('Task Completed!', {
            body: `"${taskTitle}" marked as complete!`,
            tag: 'task-complete',
            url: 'dashboard.php'
        });
    }
}

// Initialize on page load
const notifications = new SimpleNotifications();

console.log('Notification system loaded!');
