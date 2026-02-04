<?php
if (!defined('ANON_ALLOWED_ACCESS')) exit;

$post = $post ?? $this->post();
if (!$post || $post->get('comment_status', 'open') !== 'open') {
  return;
}
?>
<section class="card bg-base-100 shadow-md">
  <div class="card-body">
    <div class="mb-4">
      <div id="comments-app">
        <div class="comments-root">
          <h2 class="text-xl font-bold mb-4">评论 {{ comments.length }}</h2>

          <div v-if="message" class="alert mb-4" :class="messageType === 'error' ? 'alert-warning' : 'alert-info'" role="status">
            <span>{{ message }}</span>
          </div>

          <p v-if="!comments.length" class="text-base-content/60 mb-6">暂无评论，来抢沙发～</p>
          <div v-else class="space-y-4 mb-8" aria-label="评论列表">
            <template v-for="c in topLevelComments" :key="c.id">
              <div class="chat chat-start">
                <div class="chat-image avatar">
                  <div class="w-10 rounded-full bg-base-300">
                    <img v-if="c.avatar" :src="c.avatar" :alt="(c.name || '')">
                    <span v-else class="flex w-full h-full items-center justify-center text-base-content/70 font-semibold text-sm">{{ (c.name && c.name[0]) || '?' }}</span>
                  </div>
                </div>
                <div class="chat-header">
                  <a v-if="c.url && c.url.trim()" :href="c.url" class="link link-hover font-medium" rel="nofollow noopener">{{ c.name || '?' }}</a>
                  <span v-else class="font-medium">{{ c.name || '?' }}</span>
                  <time class="text-xs opacity-50 ml-1">{{ formatDate(c.created_at) }}</time>
                  <button type="button" class="btn btn-ghost btn-xs opacity-70 hover:opacity-100 ml-1" @click="setReplyTo(c)">回复</button>
                </div>
                <div class="chat-bubble chat-bubble-primary">{{ c.content }}</div>
              </div>
              <template v-for="r in (c.children || [])" :key="r.id">
                <div class="chat chat-start pl-8">
                  <div class="chat-image avatar">
                    <div class="w-8 rounded-full bg-base-300">
                      <img v-if="r.avatar" :src="r.avatar" :alt="(r.name || '')">
                      <span v-else class="flex w-full h-full items-center justify-center text-base-content/70 font-semibold text-xs">{{ (r.name && r.name[0]) || '?' }}</span>
                    </div>
                  </div>
                  <div class="chat-header text-sm">
                    <a v-if="r.url && r.url.trim()" :href="r.url" class="link link-hover" rel="nofollow noopener">{{ r.name || '?' }}</a>
                    <span v-else>{{ r.name || '?' }}</span>
                    <span v-if="r.reply_to_name" class="text-primary opacity-80">回复 @{{ r.reply_to_name }}</span>
                    <time class="text-xs opacity-50 ml-1">{{ formatDate(r.created_at) }}</time>
                    <button type="button" class="btn btn-ghost btn-xs opacity-70 hover:opacity-100 ml-1" @click="setReplyTo(c)">回复</button>
                  </div>
                  <div class="chat-bubble chat-bubble-secondary text-sm">{{ r.content }}</div>
                </div>
              </template>
            </template>
          </div>

          <form @submit.prevent="submitComment" class="comment-form space-y-4" aria-label="发表评论">
            <p v-if="replyingTo" class="text-sm text-base-content/70 mb-1">
              回复 <span class="text-primary font-medium">@{{ replyingTo.name }}</span>
              <button type="button" class="btn btn-ghost btn-xs ml-1" @click="cancelReply">取消</button>
            </p>

            <template v-if="isLoggedIn">
              <p v-if="!replyingTo" class="text-sm text-base-content/70 mb-1">以 <strong>{{ currentUser.name || '登录用户' }}</strong> 身份评论</p>
            </template>
            <template v-else>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="form-control">
                  <label for="comment-name" class="label"><span class="label-text">名称</span><span class="label-text-alt text-error">*</span></label>
                  <input id="comment-name" type="text" v-model="form.name" class="input input-bordered w-full input-sm md:input-md" required maxlength="255" placeholder="您的称呼" autocomplete="nickname">
                </div>
                <div class="form-control">
                  <label for="comment-email" class="label"><span class="label-text">邮箱</span><span class="label-text-alt text-error">*</span></label>
                  <input id="comment-email" type="email" v-model="form.email" class="input input-bordered w-full input-sm md:input-md" required maxlength="255" placeholder="用于接收回复通知（不公开）" autocomplete="email">
                </div>
              </div>
              <div class="form-control">
                <label for="comment-url" class="label"><span class="label-text">网址</span><span class="label-text-alt text-base-content/50">选填</span></label>
                <input id="comment-url" type="url" v-model="form.url" class="input input-bordered w-full input-sm md:input-md" maxlength="500" placeholder="https://" autocomplete="url">
              </div>
            </template>
            <div class="form-control">
              <label for="comment-content" class="label"><span class="label-text">评论内容</span><span class="label-text-alt text-error">*</span></label>
              <textarea id="comment-content" v-model="form.content" class="comment-textarea textarea textarea-bordered w-full" rows="4" required placeholder="写下您的评论…" aria-describedby="comment-content-hint"></textarea>
              <p id="comment-content-hint" class="label-text-alt mt-1 text-base-content/60">支持换行，请勿包含敏感信息。</p>
            </div>
            <div v-if="captchaEnabled && !isLoggedIn" class="form-control">
              <label for="comment-captcha" class="label">
                <span class="label-text">验证码</span>
                <span class="label-text-alt text-error">*</span>
              </label>
              <div class="flex gap-2 items-end">
                <div class="flex-1">
                  <input id="comment-captcha" type="text" v-model="form.captcha" class="input input-bordered w-full input-sm md:input-md" required maxlength="10" placeholder="请输入验证码" autocomplete="off">
                </div>
                <div class="captcha-image-wrapper" style="cursor: pointer;" @click="refreshCaptcha" :title="'点击刷新验证码'">
                  <img v-if="captchaImage && !captchaLoading" :src="captchaImage" alt="验证码" style="height: 40px; border: 1px solid #e5e7eb; border-radius: 4px;">
                  <div v-else-if="captchaLoading" class="loading loading-spinner loading-sm" style="height: 40px; width: 120px; display: flex; align-items: center; justify-content: center;"></div>
                  <div v-else style="height: 40px; width: 120px; border: 1px solid #e5e7eb; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 12px;">点击加载</div>
                </div>
              </div>
            </div>
            <div class="pt-1">
              <button type="submit" class="btn btn-primary btn-sm md:btn-md" :disabled="loading">{{ loading ? '提交中…' : '提交评论' }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>