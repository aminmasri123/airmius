<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import messages from '@/i18n/welcomeShowcase.json'

const props = defineProps({
    variant: { type: String, required: true },
    joinUrl: { type: String, required: true },
})
const { locale } = useI18n()
const copy = computed(() => messages[locale.value.split('-')[0]] || messages.en)
const root = ref(null)
const motionEnabled = ref(false)
const paused = ref(false)
const ready = ref(false)
let observer
let preference
const handlePreference = () => {
    motionEnabled.value = !preference.matches
}
onMounted(() => {
    preference = window.matchMedia('(prefers-reduced-motion: reduce)')
    handlePreference()
    preference.addEventListener('change', handlePreference)
    if (!('IntersectionObserver' in window)) return
    observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible')
                observer.unobserve(entry.target)
            }
        })
    }, { threshold: 0.08 })
    root.value.querySelectorAll('[data-reveal]').forEach(element => observer.observe(element))
    ready.value = true
})
onBeforeUnmount(() => {
    observer?.disconnect()
    preference?.removeEventListener('change', handlePreference)
})
const exerciseIcons = ['la-walking', 'la-dumbbell', 'la-running']
</script>

<template>
    <section :id="`showcase-${variant}`" ref="root" class="welcome-showcase" :class="{ 'reveal-ready': ready && motionEnabled, 'motion-paused': paused || !motionEnabled }" :aria-labelledby="`showcase-title-${variant}`">
        <div class="showcase-inner">
            <header class="showcase-heading" data-reveal>
                <div>
                    <p class="showcase-eyebrow">{{ copy[variant].eyebrow }}</p>
                    <h2 :id="`showcase-title-${variant}`">{{ copy[variant].title }}</h2>
                    <p class="showcase-intro">{{ copy[variant].intro }}</p>
                </div>
                <button v-if="variant === 'platform' && motionEnabled" class="motion-toggle" type="button" :aria-pressed="paused" @click="paused = !paused">
                    <i :class="['las', paused ? 'la-play' : 'la-pause']" aria-hidden="true"></i>
                    {{ paused ? copy.play : copy.pause }}
                </button>
            </header>

            <div v-if="variant === 'platform'" class="platform-stage" data-reveal>
                <div class="desktop-preview" :aria-label="copy.preview">
                    <aside class="preview-sidebar" aria-hidden="true">
                        <img src="/img/logo/Airmius-Logo-Dark.png" alt="" width="135" height="35">
                        <div v-for="(label, index) in copy.navigation" :key="label" :class="{ selected: index === 0 }">
                            <i :class="['las', ['la-stream', 'la-dumbbell', 'la-users', 'la-calendar'][index]]"></i><span>{{ label }}</span>
                        </div>
                    </aside>
                    <div class="feed-preview">
                        <div class="preview-top"><strong>{{ copy.world }}</strong><span class="demo-label">{{ copy.example }}</span></div>
                        <article class="preview-post">
                            <div class="post-author"><span class="avatar">{{ copy.teamInitials }}</span><div><strong>{{ copy.team }}</strong><small>{{ copy.postTime }}</small></div><i class="las la-ellipsis-h" aria-hidden="true"></i></div>
                            <img class="post-photo" src="/img/login/airmius-auth-community-events.png" :alt="copy.photoAlt" width="941" height="1672" loading="lazy" decoding="async">
                            <div class="post-copy"><strong>{{ copy.post }}</strong><p>{{ copy.postText }}</p></div>
                            <div class="post-actions"><span><i class="las la-heart" aria-hidden="true"></i> 24</span><span><i class="las la-comment" aria-hidden="true"></i> 3</span><i class="las la-share" aria-hidden="true"></i></div>
                        </article>
                        <div class="feed-status"><span class="status-dot"></span>{{ copy.connected }}</div>
                    </div>
                </div>
                <div class="phone-preview" :aria-label="copy.training">
                    <div class="phone-notch" aria-hidden="true"></div>
                    <div class="phone-top"><i class="las la-angle-left" aria-hidden="true"></i><strong>{{ copy.training }}</strong><i class="las la-calendar" aria-hidden="true"></i></div>
                    <div class="phone-cover"><i class="las la-running" aria-hidden="true"></i><span>{{ copy.session }}</span></div>
                    <div class="phone-body"><span class="demo-label">{{ copy.example }}</span><h3>{{ copy.today }}</h3><p>{{ copy.progress }}</p><div class="progress-track"><span></span></div>
                        <div v-for="(exercise, index) in copy.exercises" :key="exercise" class="phone-exercise"><i :class="['las', exerciseIcons[index]]" aria-hidden="true"></i><span>{{ exercise }}</span><i :class="['las', index < 2 ? 'la-check-circle completed' : 'la-circle']" aria-hidden="true"></i></div>
                        <span class="phone-next">{{ copy.step }}</span>
                    </div>
                    <div class="phone-bottom" aria-hidden="true"><i class="las la-home"></i><i class="las la-dumbbell"></i><i class="las la-users"></i></div>
                </div>
                <div class="floating-note"><i class="las la-check-circle" aria-hidden="true"></i>{{ copy.organized }}</div>
            </div>

            <div v-else-if="variant === 'community'" class="community-grid">
                <article class="community-card" data-reveal>
                    <div class="community-visual shared-moment"><img src="/img/login/airmius-auth-community-events.png" :alt="copy.photoAlt" width="941" height="1672" loading="lazy" decoding="async"><span class="photo-caption"><i class="las la-heart" aria-hidden="true"></i>{{ copy.together }}</span></div>
                    <div class="community-copy"><span class="feature-number">01</span><h3>{{ copy.community.cards[0].title }}</h3><p>{{ copy.community.cards[0].text }}</p></div>
                </article>
                <article class="community-card" data-reveal>
                    <div class="community-visual profile-visual"><span class="profile-orbit orbit-one" aria-hidden="true"></span><span class="profile-orbit orbit-two" aria-hidden="true"></span><div class="profile-card"><span class="profile-avatar"><i class="las la-user" aria-hidden="true"></i></span><strong>{{ copy.profile }}</strong><span>{{ copy.profileRole }}</span><div class="profile-tags"><span>{{ copy.running }}</span><span>{{ copy.fitness }}</span></div><span class="connect-label"><i class="las la-user-plus" aria-hidden="true"></i>{{ copy.connect }}</span></div></div>
                    <div class="community-copy"><span class="feature-number">02</span><h3>{{ copy.community.cards[1].title }}</h3><p>{{ copy.community.cards[1].text }}</p></div>
                </article>
                <article class="community-card" data-reveal>
                    <div class="community-visual event-visual"><div class="event-ticket"><span class="event-icon"><i class="las la-calendar-check" aria-hidden="true"></i></span><small>{{ copy.team }}</small><strong>{{ copy.event }}</strong><span>{{ copy.eventTime }}</span><div class="event-people" aria-hidden="true"><span>{{ copy.demoPeople.lena.initials }}</span><span>{{ copy.demoPeople.jonas.initials }}</span><span>{{ copy.attendeeInitials[2] }}</span><span>+3</span></div><span class="event-confirmation"><i class="las la-check" aria-hidden="true"></i>{{ copy.attending }}</span></div></div>
                    <div class="community-copy"><span class="feature-number">03</span><h3>{{ copy.community.cards[2].title }}</h3><p>{{ copy.community.cards[2].text }}</p></div>
                </article>
            </div>

            <div v-else class="tools-list">
                <article v-for="(tool, index) in copy.tools.cards" :key="tool.title" class="tool-row" data-reveal>
                    <div class="tool-copy"><span class="tool-icon"><i :class="['las', ['la-dumbbell', 'la-calendar', 'la-comments'][index]]" aria-hidden="true"></i></span><h3>{{ tool.title }}</h3><p>{{ tool.text }}</p></div>
                    <div class="tool-preview" :aria-label="copy.preview">
                        <div class="tool-preview-heading"><strong>{{ [copy.weekPlan, copy.week, copy.teamChat][index] }}</strong><span class="demo-label">{{ copy.example }}</span></div>
                        <template v-if="index === 0"><div v-for="(exercise, exerciseIndex) in copy.exercises" :key="exercise" class="exercise-row"><i :class="['las', exerciseIcons[exerciseIndex]]" aria-hidden="true"></i><strong>{{ exercise }}</strong><span>{{ copy.prescriptions[exerciseIndex] }}</span></div></template>
                        <div v-else-if="index === 1" class="week-preview"><div v-for="(day, dayIndex) in copy.days" :key="day" class="week-day"><span>{{ day }}</span><strong>{{ 16 + dayIndex }}</strong><div v-if="dayIndex === 1 || dayIndex === 3" class="calendar-session"><span>18:00</span><strong>{{ dayIndex === 1 ? copy.training : copy.team }}</strong></div><span v-else class="calendar-empty" aria-hidden="true">—</span></div></div>
                        <div v-else class="chat-preview"><div class="chat-bubble"><span class="chat-avatar">{{ copy.demoPeople.lena.initials }}</span><p><strong>{{ copy.demoPeople.lena.name }}</strong>{{ copy.chat[0] }}</p></div><div class="chat-bubble reply"><p><strong>{{ copy.demoPeople.alex.name }}</strong>{{ copy.chat[1] }}</p><i class="las la-check-double" aria-hidden="true"></i></div><div class="chat-bubble"><span class="chat-avatar">{{ copy.demoPeople.jonas.initials }}</span><p><strong>{{ copy.demoPeople.jonas.name }}</strong>{{ copy.chat[2] }}</p></div></div>
                    </div>
                </article>
            </div>

            <div class="showcase-footer" data-reveal><span>{{ copy.exampleNote }}</span><a :href="props.joinUrl">{{ copy.join }}<i class="las la-arrow-right" aria-hidden="true"></i></a></div>
        </div>
    </section>
</template>

<style scoped>
.welcome-showcase {
    padding:clamp(3.5rem,7vw,6rem) 1rem;
    border-top:1px solid rgb(var(--color-border,220 230 238)/.45);
    color:inherit;
    overflow:clip
}
.showcase-inner {
    max-width:1180px;
    margin-inline:auto
}
.showcase-heading {
    display:flex;
    justify-content:space-between;
    align-items:start;
    gap:1.5rem;
    margin-bottom:2.5rem
}
.showcase-eyebrow {
    font-size:.73rem;
    font-weight:800;
    letter-spacing:.14em;
    text-transform:uppercase;
    color:#0895bb;
    margin:0 0 .8rem
}
.showcase-heading h2 {
    font-size:clamp(1.85rem,3.4vw,3rem);
    font-weight:800;
    letter-spacing:-.035em;
    line-height:1.15;
    margin:0;
    max-width:780px
}
.showcase-intro {
    font-size:1.03rem;
    line-height:1.75;
    max-width:650px;
    margin:1rem 0 0;
    opacity:.75
}
.motion-toggle {
    display:inline-flex;
    align-items:center;
    gap:.45rem;
    flex-shrink:0;
    border:1px solid #cddde7;
    border-radius:999px;
    padding:.65rem .85rem;
    font-size:.75rem;
    background:transparent;
    color:inherit
}
.motion-toggle:hover {
    border-color:#079eca
}
.motion-toggle:focus-visible,.showcase-footer a:focus-visible {
    outline:3px solid #0ba8d5;
    outline-offset:5px
}
.platform-stage {
    position:relative;
    isolation:isolate;
    min-height:570px;
    padding:36px 145px 65px 40px;
    background:linear-gradient(125deg,#eaf7ff,#f4fbfb 70%,#e5f6f0);
    border:1px solid #dceaf0;
    border-radius:28px;
    color:#152d3d
}
.desktop-preview {
    display:grid;
    grid-template-columns:155px minmax(0,1fr);
    border:7px solid white;
    border-radius:19px;
    background:#f6f9fb;
    overflow:hidden;
    box-shadow:0 22px 65px #1d53631c;
    transform:rotate(-1deg)
}
.preview-sidebar {
    padding:21px 12px;
    background:#112c3c;
    color:#d9e7ef
}
.preview-sidebar img {
    width:125px;
    height:auto;
    max-height:40px;
    object-fit:contain;
    margin-bottom:30px
}
.preview-sidebar>div {
    display:flex;
    gap:10px;
    align-items:center;
    padding:12px 8px;
    margin:5px 0;
    border-radius:7px;
    font-size:11px
}
.preview-sidebar i {
    font-size:19px
}
.preview-sidebar .selected {
    background:#079fc9;
    color:white
}
.feed-preview {
    padding:21px;
    min-width:0
}
.preview-top {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    margin-bottom:18px;
    font-size:18px
}
.demo-label {
    font-size:10px;
    letter-spacing:.05em;
    font-weight:500;
    color:#5b7786
}
.preview-post {
    border:1px solid #e0e8ed;
    border-radius:12px;
    background:white;
    overflow:hidden
}
.post-author {
    display:flex;
    align-items:center;
    gap:9px;
    padding:12px;
    font-size:11px
}
.avatar {
    width:32px;
    height:32px;
    display:grid;
    place-items:center;
    background:#dcefe9;
    color:#246d57;
    border-radius:50%;
    font-weight:800
}
.post-author small {
    display:block;
    color:#74858f;
    font-size:10px;
    margin-top:2px
}
.post-author>i {
    margin-inline-start:auto;
    font-size:20px
}
.post-photo {
    width:100%;
    height:190px;
    object-fit:cover;
    object-position:50% 42%;
    display:block
}
.post-copy {
    padding:13px 14px 2px;
    font-size:12px
}
.post-copy p {
    margin:5px 0;
    color:#647989;
    font-size:11px;
    line-height:1.5
}
.post-actions {
    display:flex;
    gap:19px;
    align-items:center;
    padding:12px 14px;
    color:#597286;
    font-size:11px
}
.post-actions span {
    display:flex;
    align-items:center;
    gap:5px
}
.post-actions i {
    font-size:19px
}
.post-actions>i {
    margin-inline-start:auto
}
.feed-status {
    display:flex;
    align-items:center;
    gap:8px;
    font-size:10px;
    margin-top:15px;
    color:#617b88
}
.status-dot {
    width:6px;
    height:6px;
    border-radius:50%;
    background:#39b988
}
.phone-preview {
    position:absolute;
    inset-inline-end:35px;
    top:95px;
    width:212px;
    background:#f9fcfd;
    color:#172d3b;
    border:6px solid #233544;
    border-radius:31px;
    box-shadow:0 18px 35px #173d4833;
    overflow:hidden;
    transform:rotate(3deg)
}
.phone-notch {
    width:64px;
    height:13px;
    background:#233544;
    border-radius:0 0 10px 10px;
    margin:0 auto
}
.phone-top {
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:17px 10px;
    font-size:10px
}
.phone-cover {
    margin:0 10px;
    padding:20px 12px;
    background:linear-gradient(120deg,#d2f0f7,#dff4e9);
    border-radius:8px;
    display:flex;
    align-items:center;
    gap:9px;
    font-weight:700;
    font-size:12px
}
.phone-cover>i {
    font-size:34px;
    color:#0398bd
}
.phone-body {
    padding:15px 12px
}
.phone-body h3 {
    font-size:13px;
    font-weight:700;
    margin:9px 0
}
.phone-body p {
    font-size:10px;
    color:#6b818c;
    margin-bottom:8px
}
.progress-track {
    height:4px;
    background:#e5edf0;
    border-radius:8px;
    overflow:hidden;
    margin-bottom:15px
}
.progress-track span {
    display:block;
    height:100%;
    background:#25b88a;
    width:66.666%
}
.phone-exercise {
    display:flex;
    align-items:center;
    gap:7px;
    padding:13px 0;
    border-bottom:1px solid #e7eef2;
    font-size:10px
}
.phone-exercise>i {
    font-size:17px
}
.phone-exercise>i:last-child {
    margin-inline-start:auto;
    color:#97a9b3
}
.phone-exercise>i.completed {
    color:#1aaa77
}
.phone-next {
    display:block;
    background:#dff4fa;
    color:#087a9a;
    text-align:center;
    font-size:10px;
    padding:10px;
    border-radius:6px;
    margin-top:15px
}
.phone-bottom {
    display:flex;
    justify-content:space-around;
    padding:12px;
    border-top:1px solid #e2ebef;
    color:#0c99be
}
.floating-note {
    position:absolute;
    bottom:22px;
    inset-inline-start:55px;
    display:flex;
    gap:10px;
    align-items:center;
    background:white;
    border:1px solid #d9e9ec;
    border-radius:12px;
    padding:12px 20px;
    font-size:12px;
    box-shadow:0 8px 24px #1c536010
}
.floating-note i {
    color:#1aaf7d;
    font-size:21px
}
.community-grid {
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:24px
}
.community-card {
    min-width:0
}
.community-visual {
    height:260px;
    border:1px solid #dfebf0;
    border-radius:18px;
    overflow:hidden;
    background:#eff8fc;
    position:relative;
    color:#17384c
}
.shared-moment>img {
    width:100%;
    height:100%;
    object-fit:cover;
    object-position:50% 43%;
    display:block
}
.photo-caption {
    position:absolute;
    bottom:16px;
    inset-inline-start:16px;
    inset-inline-end:16px;
    border-radius:9px;
    background:#ffffffee;
    display:flex;
    gap:9px;
    align-items:center;
    padding:12px;
    font-size:12px;
    font-weight:700
}
.photo-caption i {
    font-size:23px;
    color:#df5b72
}
.community-copy {
    padding:22px 3px 0
}
.feature-number {
    font-size:11px;
    color:#079bc3;
    font-weight:800;
    letter-spacing:.1em
}
.community-copy h3 {
    font-size:1.22rem;
    font-weight:750;
    margin:8px 0
}
.community-copy p {
    font-size:.93rem;
    line-height:1.75;
    opacity:.75;
    margin:0
}
.profile-visual {
    display:grid;
    place-items:center;
    background:linear-gradient(140deg,#eaf6fc,#f4fbf8)
}
.profile-card {
    display:flex;
    align-items:center;
    flex-direction:column;
    position:relative;
    z-index:1;
    background:white;
    border:1px solid #e1ebed;
    box-shadow:0 8px 30px #21495b0a;
    border-radius:15px;
    padding:17px 23px;
    min-width:185px;
    max-width:92%;
    font-size:11px;
    gap:6px
}
.profile-card>strong {
    font-size:16px
}
.profile-card>span:not(.profile-avatar):not(.connect-label) {
    color:#6e818c;
    text-align:center
}
.profile-avatar {
    height:48px;
    width:48px;
    display:grid;
    place-items:center;
    font-size:29px;
    background:#dff0ed;
    color:#2b8b76;
    border-radius:50%;
    margin-bottom:4px
}
.profile-tags {
    display:flex;
    gap:5px;
    margin-top:5px
}
.profile-tags span {
    font-size:9px;
    background:#f1f7fa;
    border-radius:20px;
    padding:5px 8px
}
.connect-label {
    display:flex;
    gap:7px;
    align-items:center;
    justify-content:center;
    width:100%;
    margin-top:6px;
    background:#069fc7;
    color:white;
    padding:8px;
    border-radius:6px;
    font-size:11px
}
.profile-orbit {
    position:absolute;
    width:340px;
    height:340px;
    border:1px solid #c9e7eb;
    border-radius:50%
}
.orbit-two {
    width:260px;
    height:260px
}
.event-visual {
    display:grid;
    place-items:center;
    background:linear-gradient(145deg,#edf7f0,#e6f6fc)
}
.event-ticket {
    background:white;
    max-width:90%;
    width:240px;
    border:1px solid #d9e9e7;
    border-radius:14px;
    padding:18px;
    display:flex;
    flex-direction:column;
    align-items:start;
    gap:7px;
    font-size:11px;
    transform:rotate(-3deg);
    box-shadow:0 10px 25px #2e666d0b
}
.event-icon {
    color:#259b79;
    font-size:27px
}
.event-ticket small {
    color:#7c8c91
}
.event-ticket>strong {
    font-size:16px
}
.event-people {
    display:flex;
    margin:8px 0 3px
}
.event-people span {
    width:27px;
    height:27px;
    border-radius:50%;
    background:#e5f0f4;
    border:2px solid white;
    margin-inline-end:-5px;
    display:grid;
    place-items:center;
    font-size:8px
}
.event-confirmation {
    color:#18865f;
    background:#e9f7ee;
    padding:6px 10px;
    border-radius:6px;
    display:flex;
    gap:5px
}
.tools-list {
    display:grid;
    gap:28px
}
.tool-row {
    display:grid;
    grid-template-columns:minmax(0, .9fr) minmax(0,1.1fr);
    gap:60px;
    align-items:center;
    padding:28px;
    background:linear-gradient(115deg,#f4fbff80,transparent);
    border:1px solid #d9e8ef;
    border-radius:20px
}
.tool-row:nth-child(even) .tool-copy {
    order:2
}
.tool-row:nth-child(even) .tool-preview {
    order:1
}
.tool-copy {
    padding:12px
}
.tool-icon {
    display:grid;
    place-items:center;
    width:42px;
    height:42px;
    border-radius:12px;
    background:#e0f7ee;
    color:#1f9b78;
    font-size:26px;
    margin-bottom:15px
}
.tool-copy h3 {
    font-size:1.4rem;
    font-weight:750;
    margin:0 0 12px;
    letter-spacing:-.02em
}
.tool-copy p {
    opacity:.75;
    font-size:.95rem;
    line-height:1.8;
    margin:0
}
.tool-preview {
    min-width:0;
    background:white;
    color:#1e3a4b;
    border:1px solid #e1eaf0;
    border-radius:12px;
    box-shadow:0 8px 22px #1641560b;
    overflow:hidden
}
.tool-preview-heading {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    font-size:12px;
    padding:18px;
    border-bottom:1px solid #edf1f4
}
.exercise-row {
    display:grid;
    grid-template-columns:25px minmax(0,1fr) auto;
    align-items:center;
    gap:8px;
    padding:14px 18px;
    border-bottom:1px solid #edf1f4;
    font-size:11px
}
.exercise-row>i {
    font-size:20px;
    color:#0a9ac0
}
.exercise-row>span {
    font-size:10px;
    color:#6d818c
}
.week-preview {
    display:grid;
    grid-template-columns:repeat(5,minmax(0,1fr));
    min-height:165px
}
.week-day {
    border-inline-end:1px solid #edf2f5;
    text-align:center;
    padding:12px 3px;
    font-size:10px
}
.week-day>span,.week-day>strong {
    display:block
}
.week-day>strong {
    margin-top:7px;
    font-size:15px
}
.calendar-session {
    display:flex;
    flex-direction:column;
    text-align:start;
    background:#e0f5f9;
    border-inline-start:2px solid #18accd;
    border-radius:4px;
    margin-top:18px;
    padding:6px 3px;
    font-size:9px;
    gap:4px;
    overflow-wrap:anywhere
}
.calendar-empty {
    margin-top:30px;
    color:#b7c7d0
}
.chat-preview {
    padding:17px;
    display:grid;
    gap:13px
}
.chat-bubble {
    display:flex;
    align-items:start;
    gap:8px;
    font-size:10px
}
.chat-avatar {
    display:grid;
    place-items:center;
    flex-shrink:0;
    width:26px;
    height:26px;
    background:#e7f0f4;
    border-radius:50%;
    font-size:8px
}
.chat-bubble p {
    background:#f1f6f9;
    border-radius:0 9px 9px;
    padding:9px 12px;
    margin:0;
    line-height:1.6
}
.chat-bubble strong {
    display:block;
    font-size:9px;
    color:#6290a3
}
.chat-bubble.reply {
    justify-content:end;
    align-items:end
}
.chat-bubble.reply p {
    background:#e2f5ef;
    border-radius:9px 0 9px 9px
}
.chat-bubble.reply>i {
    color:#089abc;
    font-size:15px
}
.showcase-footer {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:20px;
    margin-top:25px;
    font-size:11px
}
.showcase-footer>span {
    opacity:.6;
    max-width:580px;
    line-height:1.6
}
.showcase-footer>a {
    display:inline-flex;
    align-items:center;
    gap:10px;
    flex-shrink:0;
    color:#0797bf;
    font-weight:700;
    font-size:13px;
    text-decoration:none;
    padding:8px 0
}
.showcase-footer>a:hover {
    text-decoration:underline
}
/* Content stays visible if observers or JavaScript animations are unavailable. */
@media(prefers-reduced-motion:no-preference) {
    .reveal-ready [data-reveal] {
    opacity:0;
    transform:translateY(24px);
    transition:opacity .7s ease,transform .7s cubic-bezier(.2,.65,.3,1)
}
.reveal-ready [data-reveal].is-visible {
    opacity:1;
    transform:translateY(0)
}
.community-card:nth-child(2) {
    transition-delay:90ms
}
.community-card:nth-child(3) {
    transition-delay:180ms
}
.reveal-ready .platform-stage.is-visible .phone-preview {
    animation:phone-settle 1s .2s both
}
.reveal-ready .platform-stage.is-visible .progress-track span {
    animation:progress-fill 1.3s .4s both
}
.reveal-ready .platform-stage.is-visible .floating-note {
    animation:note-enter .8s .7s both
}
.motion-paused * {
    animation:none!important;
    transition:none!important
}
.motion-paused [data-reveal] {
    opacity:1!important;
    transform:none!important;
    transition:none!important
}
}
@keyframes phone-settle {
    from {
    opacity:0;
    transform:translateY(25px) rotate(6deg)
}
to {
    opacity:1;
    transform:translateY(0) rotate(3deg)
}
}
@keyframes progress-fill {
    from {
    width:0
}
to {
    width:66.666%
}
}
@keyframes note-enter {
    from {
    opacity:0;
    transform:translateY(10px)
}
to {
    opacity:1;
    transform:translateY(0)
}
}
@media(max-width:1000px) {
    .platform-stage {
    padding:25px 115px 70px 20px
}
.preview-sidebar {
    display:none
}
.desktop-preview {
    grid-template-columns:minmax(0,1fr)
}
.phone-preview {
    inset-inline-end:20px;
    width:195px;
    top:110px
}
.community-grid {
    gap:16px
}
.tool-row {
    gap:25px
}
.tool-copy {
    padding:0
}
}
@media(max-width:640px) {
    .showcase-heading {
    display:block;
    margin-bottom:22px
}
.motion-toggle {
    margin-top:17px
}
.platform-stage {
    min-height:0;
    padding:14px 14px 20px;
    border-radius:18px;
    display:grid;
    grid-template-columns:minmax(0,1fr);
    gap:20px
}
.desktop-preview {
    transform:none;
    border-width:4px;
    border-radius:13px
}
.feed-preview {
    padding:14px
}
.preview-top {
    font-size:16px
}
.post-photo {
    height:175px
}
.phone-preview {
    position:relative;
    inset:auto;
    top:auto;
    width:210px;
    max-width:100%;
    justify-self:center;
    transform:rotate(3deg);
    margin:0 0 8px
}
.floating-note {
    position:static;
    justify-self:center;
    padding:10px 12px;
    font-size:11px;
    max-width:100%
}
.community-grid {
    grid-template-columns:minmax(0,1fr);
    gap:32px
}
.community-visual {
    height:265px
}
.community-copy {
    padding-top:15px
}
.tool-row {
    grid-template-columns:minmax(0,1fr);
    gap:22px;
    padding:20px 14px
}
.tool-row:nth-child(even) .tool-copy,.tool-row:nth-child(even) .tool-preview {
    order:initial
}
.tool-copy h3 {
    font-size:1.25rem
}
.tool-preview-heading {
    padding:13px 10px
}
.exercise-row {
    padding:13px 10px;
    gap:5px;
    font-size:10px
}
.exercise-row>span {
    font-size:9px;
    max-width:75px;
    text-align:end
}
.chat-preview {
    padding:12px
}
.showcase-footer {
    align-items:start;
    flex-direction:column;
    gap:8px
}
.showcase-intro {
    font-size:.96rem
}
.week-day {
    font-size:9px
}
.calendar-session {
    font-size:8px
}
}
</style>
