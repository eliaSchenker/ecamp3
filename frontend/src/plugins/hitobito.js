import { getEnv } from '@/environment.js'

/**
 * Hitobito providers for which event import is supported.
 */
export const HITOBITO_PROVIDERS = ['pbsmidata']

const PROVIDER_ICONS = {
  pbsmidata: '$pbs',
}

const PROVIDER_ICON_COLORS = {
  pbsmidata: '#521d3a',
}

export function isValidProvider(provider) {
  return HITOBITO_PROVIDERS.includes(provider)
}

export function providerIcon(provider) {
  return PROVIDER_ICONS[provider] ?? null
}

export function providerIconColor(provider) {
  return PROVIDER_ICON_COLORS[provider] ?? null
}

export function hitobitoEventsUri(provider, eventId = null) {
  const collection = `/hitobito/${provider}/events`
  return eventId === null ? collection : `${collection}/${eventId}`
}

export function redirectToHitobitoAuthorization(provider, callbackPath) {
  markAuthorizationAttempt(provider)
  const callback = encodeURIComponent(callbackPath)
  const url = `${getEnv().API_ROOT_URL}/hitobito/${provider}/oauth?callback=${callback}`
  window.location.replace(url)
}

export function isAccessTokenInvalidError(error) {
  return (
    error?.response?.status === 403 &&
    error?.response?.data?.type === '/errors/hitobito-access-token-invalid'
  )
}

/**
 * In case Hitobito OAuth fails (error or user denies access), we don't want to immediately redirect again.
 * To avoid this redirection loop, an entry is made into the session storage, indicating that an authorization redirect
 * to Hitobito has already happened.
 */

function attemptKey(provider) {
  return `hitobito_auth_attempt_${provider}`
}

export function markAuthorizationAttempt(provider) {
  window.sessionStorage.setItem(attemptKey(provider), '1')
}

export function hasAttemptedAuthorization(provider) {
  try {
    return window.sessionStorage.getItem(attemptKey(provider)) === '1'
  } catch {
    return false
  }
}

export function clearAuthorizationAttempt(provider) {
  window.sessionStorage.removeItem(attemptKey(provider))
}
