const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_URL ?? "http://127.0.0.1:8000/api/v1";

export type ApiUserRole = {
  id: string;
  name: string;
  slug: string;
};

export type ApiUser = {
  id: string;
  name: string;
  email: string;
  email_verified_at: string | null;
  created_at: string;
  updated_at: string;
  roles?: ApiUserRole[];
};

type LoginResponse = {
  success: boolean;
  message: string;
  data: {
    user: ApiUser;
    token: string;
    token_type: string;
  };
};


type AdminPasskeyLoginChallengeResponse = {
  success: boolean;
  message: string;
  data: {
    passkey_required: true;
    transaction_id: string;
    public_key: {
      challenge: string;
      rpId?: string;
      timeout?: number;
      userVerification?: UserVerificationRequirement;
      allowCredentials?: Array<{
        type: PublicKeyCredentialType;
        id: string;
        transports?: AuthenticatorTransport[];
      }>;
    };
  };
};


export type UserDeviceVerificationChallenge = {
  device_verification_required: true;
  request_token: string;
  email: string;
  expires_in: number;
};

export type UserDeviceLoginChallenge = {
  verification_required: true;
  message: string;
  data: UserDeviceVerificationChallenge;
};

export type VerifyUserDeviceResponse = {
  success: boolean;
  message: string;
  data: {
    user: ApiUser;
    token: string;
    token_type: string;
    expires_in?: number;
    device_token: string;
    device?: {
      id: string;
      device_type?: string | null;
      browser?: string | null;
      platform?: string | null;
      registered_at?: string | null;
      last_seen_at?: string | null;
    };
  };
};

export type RegisterResponse = {
  success: boolean;
  message: string;
  data: {
    user: ApiUser;
    approval_required: boolean;
    device_token: string;
  };
};

export class ApiError extends Error {
  status: number;
  code?: string;
  data?: unknown;
  errors?: Record<string, string[]>;

  constructor(
    message: string,
    status: number,
    code?: string,
    data?: unknown,
    errors?: Record<string, string[]>,
  ) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.code = code;
    this.data = data;
    this.errors = errors;
  }
}

export async function apiRequest<T>(
  path: string,
  options: RequestInit = {},
): Promise<T> {
  const token =
    typeof window !== "undefined"
      ? window.sessionStorage.getItem("crypticx_token") ??
        window.localStorage.getItem("crypticx_token")
      : null;

  const deviceToken =
    typeof window !== "undefined"
      ? window.localStorage.getItem("crypticx_device_token")
      : null;

  const headers = new Headers(options.headers);

  headers.set("Accept", "application/json");

  if (options.body && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }

  if (token) {
    headers.set("Authorization", `Bearer ${token}`);
  }

  const shouldAttachDeviceToken =
    path.startsWith("/admin/") ||
    path === "/auth/login" ||
    path === "/auth/register" ||
    path === "/auth/passkey/authenticate/verify" ||
    path.startsWith("/security/devices");

  if (
    deviceToken &&
    shouldAttachDeviceToken &&
    !headers.has("X-Device-Token")
  ) {
    headers.set("X-Device-Token", deviceToken);
  }

  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...options,
    headers,
    cache: "no-store",
  });

  const data = await response.json().catch(() => null);

  if (!response.ok) {
    const message =
      data?.message ??
      data?.errors?.email?.[0] ??
      data?.errors?.password?.[0] ??
      `API request failed with status ${response.status}.`;

    throw new ApiError(
      message,
      response.status,
      data?.code,
      data?.data,
      data?.errors,
    );
  }

  return data as T;
}

export async function verifyUserDevice(
  requestToken: string,
  code: string,
  remember: boolean,
): Promise<VerifyUserDeviceResponse> {
  const response = await apiRequest<VerifyUserDeviceResponse>(
    "/auth/device/verify",
    {
      method: "POST",
      body: JSON.stringify({
        request_token: requestToken,
        code,
      }),
    },
  );

  if (typeof window !== "undefined") {
    window.localStorage.setItem(
      "crypticx_user_device_token",
      response.data.device_token,
    );

    window.localStorage.removeItem("crypticx_token");
    window.sessionStorage.removeItem("crypticx_token");

    const storage = remember
      ? window.localStorage
      : window.sessionStorage;

    storage.setItem(
      "crypticx_token",
      response.data.token,
    );
  }

  return response;
}

export type PasswordRecoveryResponse = {
  success: boolean;
  message: string;
};

export type PasswordRecoveryVerifyResponse = {
  success: boolean;
  message: string;
  data: {
    reset_token: string;
  };
};

export async function requestPasswordReset(
  email: string,
): Promise<PasswordRecoveryResponse> {
  return apiRequest<PasswordRecoveryResponse>(
    "/auth/password/forgot",
    {
      method: "POST",
      body: JSON.stringify({ email }),
    },
  );
}

export async function verifyPasswordResetCode(
  email: string,
  code: string,
): Promise<PasswordRecoveryVerifyResponse> {
  return apiRequest<PasswordRecoveryVerifyResponse>(
    "/auth/password/verify",
    {
      method: "POST",
      body: JSON.stringify({
        email,
        code,
      }),
    },
  );
}

export async function resetPassword(
  email: string,
  resetToken: string,
  password: string,
  passwordConfirmation: string,
): Promise<PasswordRecoveryResponse> {
  return apiRequest<PasswordRecoveryResponse>(
    "/auth/password/reset",
    {
      method: "POST",
      body: JSON.stringify({
        email,
        reset_token: resetToken,
        password,
        password_confirmation: passwordConfirmation,
      }),
    },
  );
}

export async function register(
  name: string,
  email: string,
  password: string,
  passwordConfirmation: string,
): Promise<RegisterResponse> {
  const response = await apiRequest<RegisterResponse>("/auth/register", {
    method: "POST",
    body: JSON.stringify({
      name,
      email,
      password,
      password_confirmation: passwordConfirmation,
    }),
  });

  window.localStorage.removeItem("crypticx_token");
  window.sessionStorage.removeItem("crypticx_token");

  const userDeviceToken = response.data?.device_token;

  if (userDeviceToken) {
    window.localStorage.setItem(
      "crypticx_user_device_token",
      userDeviceToken,
    );
  }

  return response;
}

export async function login(
  email: string,
  password: string,
  remember: boolean,
): Promise<LoginResponse | UserDeviceLoginChallenge> {
  const userDeviceToken =
    typeof window !== "undefined"
      ? window.localStorage.getItem(
          "crypticx_user_device_token",
        )
      : null;

  const loginHeaders = new Headers();

  if (userDeviceToken) {
    loginHeaders.set(
      "X-User-Device-Token",
      userDeviceToken,
    );
  }

  let response:
    | LoginResponse
    | AdminPasskeyLoginChallengeResponse;

  try {
    response = await apiRequest<
      LoginResponse | AdminPasskeyLoginChallengeResponse
    >("/auth/login", {
      method: "POST",
      headers: loginHeaders,
      body: JSON.stringify({
        email,
        password,
      }),
    });
  } catch (error) {
    if (
      error instanceof ApiError &&
      error.status === 403 &&
      error.code === "USER_DEVICE_VERIFICATION_REQUIRED"
    ) {
      const challenge =
        error.data as UserDeviceVerificationChallenge | undefined;

      if (
        challenge?.device_verification_required === true &&
        typeof challenge.request_token === "string" &&
        typeof challenge.email === "string"
      ) {
        return {
          verification_required: true,
          message: error.message,
          data: challenge,
        };
      }
    }

    throw error;
  }

  let authenticatedResponse: LoginResponse;

  if (
    "passkey_required" in response.data &&
    response.data.passkey_required === true
  ) {
    if (typeof window === "undefined") {
      throw new Error(
        "Administrator passkey verification requires a browser.",
      );
    }

    if (
      !window.PublicKeyCredential ||
      typeof navigator.credentials?.get !== "function"
    ) {
      throw new Error(
        "Passkeys are not supported by this browser or device.",
      );
    }

    const serverOptions = response.data.public_key;

    const publicKey: PublicKeyCredentialRequestOptions = {
      ...serverOptions,
      challenge: base64UrlToArrayBuffer(
        serverOptions.challenge,
      ),
      allowCredentials: serverOptions.allowCredentials?.map(
        (credential) => ({
          ...credential,
          id: base64UrlToArrayBuffer(credential.id),
        }),
      ),
    };

    let assertion: Credential | null;

    try {
      assertion = await navigator.credentials.get({
        publicKey,
      });
    } catch {
      throw new Error(
        "Admin verification failed. Please try again.",
      );
    }

    if (!(assertion instanceof PublicKeyCredential)) {
      throw new Error(
        "Admin verification status: Unverified",
      );
    }

    const assertionResponse = assertion.response;

    if (
      !(assertionResponse instanceof AuthenticatorAssertionResponse)
    ) {
      throw new Error(
        "Admin verification status: Unverified",
      );
    }

    authenticatedResponse = await apiRequest<LoginResponse>(
      "/auth/passkey/authenticate/verify",
      {
        method: "POST",
        body: JSON.stringify({
          transaction_id: response.data.transaction_id,
          credential: {
            id: assertion.id,
            rawId: bytesToBase64Url(assertion.rawId),
            type: assertion.type,
            authenticatorAttachment:
              assertion.authenticatorAttachment ?? null,
            response: {
              clientDataJSON: bytesToBase64Url(
                assertionResponse.clientDataJSON,
              ),
              authenticatorData: bytesToBase64Url(
                assertionResponse.authenticatorData,
              ),
              signature: bytesToBase64Url(
                assertionResponse.signature,
              ),
              userHandle: assertionResponse.userHandle
                ? bytesToBase64Url(
                    assertionResponse.userHandle,
                  )
                : null,
            },
            clientExtensionResults:
              assertion.getClientExtensionResults(),
          },
        }),
      },
    );
  } else {
    authenticatedResponse = response as LoginResponse;
  }

  const storage = remember
    ? window.localStorage
    : window.sessionStorage;

  window.localStorage.removeItem("crypticx_token");
  window.sessionStorage.removeItem("crypticx_token");

  storage.setItem(
    "crypticx_token",
    authenticatedResponse.data.token,
  );

  return authenticatedResponse;
}

export async function logout(): Promise<void> {
  try {
    if (getStoredToken()) {
      await apiRequest<{ success: boolean; message: string }>("/auth/logout", {
        method: "POST",
      });
    }
  } finally {
    window.localStorage.removeItem("crypticx_token");
    window.sessionStorage.removeItem("crypticx_token");
  }
}

export function getStoredToken(): string | null {
  if (typeof window === "undefined") {
    return null;
  }

  return (
    window.sessionStorage.getItem("crypticx_token") ??
    window.localStorage.getItem("crypticx_token")
  );
}


async function downloadAuthenticatedPdf(
  path: string,
  fallbackFilename: string,
): Promise<void> {
  if (typeof window === "undefined") {
    throw new Error("PDF download is only available in the browser.");
  }

  const token = getStoredToken();

  if (!token) {
    throw new Error("Authentication required.");
  }

  const response = await fetch(`${API_BASE_URL}${path}`, {
    method: "GET",
    headers: {
      Accept: "application/pdf",
      Authorization: `Bearer ${token}`,
    },
    cache: "no-store",
  });

  if (!response.ok) {
    let message = `PDF download failed with status ${response.status}.`;

    const contentType = response.headers.get("Content-Type") ?? "";

    if (contentType.includes("application/json")) {
      const data = await response.json().catch(() => null);

      if (data?.message) {
        message = data.message;
      }
    }

    throw new Error(message);
  }

  const contentType = response.headers.get("Content-Type") ?? "";

  if (!contentType.toLowerCase().includes("application/pdf")) {
    throw new Error("Server returned an invalid PDF response.");
  }

  const blob = await response.blob();

  if (blob.size === 0) {
    throw new Error("The generated PDF is empty.");
  }

  const disposition = response.headers.get("Content-Disposition") ?? "";

  const utf8Match = disposition.match(/filename\*=UTF-8''([^;]+)/i);
  const plainMatch = disposition.match(/filename="?([^";]+)"?/i);

  let filename = fallbackFilename;

  if (utf8Match?.[1]) {
    try {
      filename = decodeURIComponent(utf8Match[1]);
    } catch {
      filename = fallbackFilename;
    }
  } else if (plainMatch?.[1]) {
    filename = plainMatch[1];
  }

  const objectUrl = URL.createObjectURL(blob);
  const link = document.createElement("a");

  link.href = objectUrl;
  link.download = filename;
  link.style.display = "none";

  document.body.appendChild(link);
  link.click();
  link.remove();

  window.setTimeout(() => {
    URL.revokeObjectURL(objectUrl);
  }, 1000);
}

export async function downloadReportPdf(
  reportId: string,
): Promise<void> {
  return downloadAuthenticatedPdf(
    `/reports/${encodeURIComponent(reportId)}/pdf`,
    `crypticx-report-${reportId.slice(0, 8)}.pdf`,
  );
}

export async function downloadAdminReportPdf(
  reportId: string,
): Promise<void> {
  return downloadAuthenticatedPdf(
    `/admin/reports/${encodeURIComponent(reportId)}/pdf`,
    `crypticx-report-${reportId.slice(0, 8)}.pdf`,
  );
}

export type AdminUserSubscriptionState = {
  subscribed: boolean;
  plan_code: string | null;
  status: string | null;
  provider: string | null;
  current_period_start: string | null;
  current_period_end: string | null;
  cancel_at_period_end: boolean;
};

export type AdminUserSubscriptionMeta = {
  effective_plan: string;
  subscription: AdminUserSubscriptionState;
};

export type PaginatedUsersResponse = {
  data: ApiUser[];
  subscription_meta?: Record<string, AdminUserSubscriptionMeta>;
  links?: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
  meta?: {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
  };
};

export async function getAdminUsers(): Promise<PaginatedUsersResponse> {
  return apiRequest<PaginatedUsersResponse>("/users");
}

export type AdminDashboardResponse = {
  success: boolean;
  data: {
    users: {
      total: number;
      verified: number;
      pending: number;
    };
    targets: {
      total: number;
      active: number;
    };
    assessments: {
      total: number;
      queued: number;
      running: number;
      completed: number;
      failed: number;
    };
    findings: {
      total: number;
      current: number;
      open: number;
      confirmed: number;
      reopened: number;
      resolved: number;
      critical: number;
      high: number;
      risk_distribution: {
        critical: number;
        high: number;
        medium: number;
        low: number;
        informational: number;
      };
      critical_or_high: number;
    };
    overall_risk: {
      score: number;
      level: string;
      highest: number;
      average: number;
      item_count: number;
    };
    system: {
      api: string;
      database: string;
      queue: string;
      scanner_workers: string;
    };
    recent_assessments: Array<{
      id: string;
      status: string;
      progress: number;
      worker_id: string | null;
      target: {
        id: string;
        name: string;
        hostname: string;
      } | null;
      created_at: string | null;
      started_at: string | null;
      completed_at: string | null;
    }>;
    scoring: {
      version: string;
      formula: string;
      source: string;
    };
  };
};

export async function getAdminDashboard(): Promise<AdminDashboardResponse> {
  return apiRequest<AdminDashboardResponse>("/admin/dashboard");
}

export type CurrentUserResponse = {
  success: boolean;
  data: {
    user: ApiUser & {
      roles?: Array<
        ApiUserRole & {
          permissions?: Array<{
            id: string;
            name: string;
            slug: string;
          }>;
        }
      >;
    };
  };
};

export async function getCurrentUser(): Promise<CurrentUserResponse> {
  return apiRequest<CurrentUserResponse>("/auth/me");
}

export type TrustedDevice = {
  id: string;
  name: string | null;
  device_type: string | null;
  browser: string | null;
  platform: string | null;
  last_ip_address: string | null;
  first_seen_at: string | null;
  last_seen_at: string | null;
  verified_at: string | null;
  expires_at: string | null;
  is_trusted: boolean;
  revoked_at: string | null;
};

export type TrustedDevicesResponse = {
  success: boolean;
  data: {
    devices: TrustedDevice[];
  };
};

export async function getTrustedDevices(): Promise<TrustedDevicesResponse> {
  return apiRequest<TrustedDevicesResponse>("/security/devices");
}

export type RegisterTrustedDeviceResponse = {
  success: boolean;
  message: string;
  data: {
    device: TrustedDevice;
    device_token: string;
  };
};

export type VerifyTrustedDeviceResponse = {
  success: boolean;
  message: string;
  data: {
    device: TrustedDevice;
  };
};

export async function registerTrustedDevice(): Promise<RegisterTrustedDeviceResponse> {
  return apiRequest<RegisterTrustedDeviceResponse>(
    "/security/devices",
    {
      method: "POST",
    },
  );
}

export async function verifyTrustedDevice(
  deviceId: string,
  deviceToken: string,
  name?: string,
): Promise<VerifyTrustedDeviceResponse> {
  return apiRequest<VerifyTrustedDeviceResponse>(
    `/security/devices/${encodeURIComponent(deviceId)}/verify`,
    {
      method: "POST",
      body: JSON.stringify({
        device_token: deviceToken,
        name: name || undefined,
      }),
    },
  );
}

export function storeTrustedDeviceCredential(
  deviceId: string,
  deviceToken: string,
): void {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.setItem(
    "crypticx_device_id",
    deviceId,
  );

  window.localStorage.setItem(
    "crypticx_device_token",
    deviceToken,
  );
}

export function clearTrustedDeviceCredential(): void {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.removeItem(
    "crypticx_device_id",
  );

  window.localStorage.removeItem(
    "crypticx_device_token",
  );
}

export function getStoredTrustedDeviceId(): string | null {
  if (typeof window === "undefined") {
    return null;
  }

  return window.localStorage.getItem(
    "crypticx_device_id",
  );
}

export async function revokeTrustedDevice(
  deviceId: string,
): Promise<{
  success: boolean;
  message: string;
}> {
  return apiRequest<{
    success: boolean;
    message: string;
  }>(`/security/devices/${deviceId}/revoke`, {
    method: "POST",
  });
}

export type ApiTarget = {
  id: string;
  user_id: string;
  name: string;
  url: string;
  hostname: string;
  scheme?: string;
  port?: number | null;
  authorization_confirmed?: boolean;
  authorization_method?: string | null;
  status?: string;
  created_at?: string;
  updated_at?: string;
};

export type CreateTargetResponse = {
  success: boolean;
  data: ApiTarget;
  message?: string;
};

export type CreateAssessmentResponse = {
  success: boolean;
  data: {
    id: string;
    user_id: string;
    target_id: string;
    profile: "discovery" | "standard" | "deep";
    status: string;
    progress: number;
    queued_at?: string | null;
    started_at?: string | null;
    completed_at?: string | null;
    worker_id?: string | null;
    configuration?: Record<string, unknown> | null;
    execution_metadata?: Record<string, unknown> | null;
    error_message?: string | null;
    target?: ApiTarget;
    findings?: ApiFinding[];
  };
  message?: string;
};

export type ApiFinding = {
  id: string;
  assessment_id: string;
  target_id: string;
  type: string;
  fingerprint: string | null;
  title: string;
  description: string;
  severity: string;
  confidence: string;
  evidence: string | null;
  evidence_data: Record<string, unknown> | null;
  remediation: string | null;
  status: string;
  created_at: string;
  updated_at: string;
};

export type FindingsResponse = {
  success: boolean;
  data: ApiFinding[];
  links?: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
  meta?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

export async function createTarget(
  name: string,
  url: string,
  authorizationMethod = "user_confirmed",
): Promise<CreateTargetResponse> {
  return apiRequest<CreateTargetResponse>("/targets", {
    method: "POST",
    body: JSON.stringify({
      name,
      url,
      authorization_confirmed: true,
      authorization_method: authorizationMethod,
    }),
  });
}

export async function createAssessment(
  targetId: string,
  profile: "discovery" | "standard" | "deep" = "standard",
): Promise<CreateAssessmentResponse> {
  return apiRequest<CreateAssessmentResponse>("/assessments", {
    method: "POST",
    body: JSON.stringify({
      target_id: targetId,
      profile,
      configuration: {},
    }),
  });
}

export async function getAssessment(
  assessmentId: string,
): Promise<CreateAssessmentResponse> {
  return apiRequest<CreateAssessmentResponse>(
    `/assessments/${assessmentId}`,
  );
}

export type AssessmentRiskAggregate = {
  score: number;
  level: string;
  highest: number;
  average: number;
  item_count: number;
};

export type AssessmentIntelligenceFinding = {
  id: string;
  fingerprint: string | null;
  type: string;
  title: string;
  severity: string;
  confidence: string;
  status: string;
};

export type AssessmentIntelligencePersistentFinding = {
  fingerprint: string;
  previous: AssessmentIntelligenceFinding;
  current: AssessmentIntelligenceFinding;
  changes: Record<
    string,
    {
      from: string | null;
      to: string | null;
    }
  >;
};

export type AssessmentIntelligenceComparison = {
  previous_assessment: {
    id: string;
    target_id: string;
    profile: string;
    status: string;
    created_at: string | null;
    completed_at: string | null;
  };
  current_assessment: {
    id: string;
    target_id: string;
    profile: string;
    status: string;
    created_at: string | null;
    completed_at: string | null;
  };
  risk: {
    previous: AssessmentRiskAggregate;
    current: AssessmentRiskAggregate;
    delta_points: number;
    trend: "increased" | "decreased" | "unchanged";
    semantics: {
      unit: "risk_points";
      probability: false;
      historical_snapshot: false;
      source: "current_lifecycle_scoring";
    };
  };
  summary: {
    previous_findings: number;
    current_findings: number;
    new: number;
    persistent: number;
    no_longer_detected: number;
    net_change: number;
  };
  new: AssessmentIntelligenceFinding[];
  persistent: AssessmentIntelligencePersistentFinding[];
  no_longer_detected: AssessmentIntelligenceFinding[];
  semantics: {
    identity: "finding_fingerprint";
    absence_means: "no_longer_detected";
    absence_does_not_prove: "resolved";
  };
};

export type AssessmentIntelligenceResponse = {
  success: boolean;
  data:
    | {
        available: false;
        reason: "no_previous_completed_assessment" | string;
        current_assessment: {
          id: string;
          target_id: string;
          profile: string;
          status: string;
          created_at: string | null;
          completed_at: string | null;
        };
      }
    | {
        available: true;
        comparison: AssessmentIntelligenceComparison;
      };
};

export async function getAssessmentIntelligence(
  assessmentId: string,
): Promise<AssessmentIntelligenceResponse> {
  return apiRequest<AssessmentIntelligenceResponse>(
    `/assessments/${assessmentId}/intelligence`,
  );
}

export async function getFindings(
  params: {
    assessment_id?: string;
    target_id?: string;
    severity?: string;
    status?: string;
    per_page?: number;
  } = {},
): Promise<FindingsResponse> {
  const search = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "") {
      search.set(key, String(value));
    }
  });

  const query = search.toString();

  return apiRequest<FindingsResponse>(
    `/findings${query ? `?${query}` : ""}`,
  );
}

export async function getTargets(params: {
  page?: number;
  per_page?: number;
} = {}) {
  const query = new URLSearchParams();

  if (params.page) query.set("page", String(params.page));
  if (params.per_page) query.set("per_page", String(params.per_page));

  const suffix = query.toString() ? `?${query.toString()}` : "";

  return apiRequest<unknown>(`/targets${suffix}`);
}


export async function getAssessments(params: {
  page?: number;
  per_page?: number;
} = {}) {
  const query = new URLSearchParams();

  if (params.page) query.set("page", String(params.page));
  if (params.per_page) query.set("per_page", String(params.per_page));

  const suffix = query.toString() ? `?${query.toString()}` : "";

  return apiRequest<unknown>(`/assessments${suffix}`);
}


export async function getFinding(id: string) {
  return apiRequest<unknown>(`/findings/${id}`);
}

export async function getFindingLifecycle(id: string) {
  return apiRequest<unknown>(`/findings/${id}/lifecycle`);
}

export async function getFindingHistory(id: string) {
  return apiRequest<unknown>(`/findings/${id}/history`);
}


export async function confirmFinding(id: string) {
  return apiRequest<unknown>(`/findings/${id}/confirm`, {
    method: "PATCH",
  });
}

export async function resolveFinding(id: string) {
  return apiRequest<unknown>(`/findings/${id}/resolve`, {
    method: "PATCH",
  });
}


export async function getFindingLifecycles(
  params: {
    severity?: string;
    status?: string;
    type?: string;
    target_id?: string;
    sort?: string;
    direction?: "asc" | "desc";
    per_page?: number;
  } = {},
) {
  const search = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== "") {
      search.set(key, String(value));
    }
  });

  const query = search.toString();

  return apiRequest<unknown>(
    `/finding-lifecycles${query ? `?${query}` : ""}`,
  );
}


export async function runDnsLookup(hostname: string) {
  return apiRequest<unknown>("/tools/dns-lookup", {
    method: "POST",
    body: JSON.stringify({ hostname }),
  });
}

export async function runWebSecurityAnalysis(url: string) {
  return apiRequest<unknown>("/tools/web-security", {
    method: "POST",
    body: JSON.stringify({ url }),
  });
}

export async function runPhishingLinkAnalysis(url: string) {
  return apiRequest<unknown>("/tools/phishing-link-analyzer", {
    method: "POST",
    body: JSON.stringify({ url }),
  });
}

export async function runApiSecurityAnalysis(
  targetId: string,
) {
  return apiRequest<unknown>("/tools/api-security", {
    method: "POST",
    body: JSON.stringify({
      target_id: targetId,
    }),
  });
}


export async function runDnsIntelligence(
  hostname: string,
  tool: "record-inspector" | "subdomain-discovery" | "dns-health"
) {
  return apiRequest<unknown>("/tools/dns-intelligence", {
    method: "POST",
    body: JSON.stringify({ hostname, tool }),
  });
}

export async function runSslTlsAnalysis(
  hostname: string,
  tool:
    | "certificate-check"
    | "tls-analysis"
    | "cipher-review"
    | "certificate-chain"
) {
  return apiRequest<unknown>("/tools/ssl-tls", {
    method: "POST",
    body: JSON.stringify({ hostname, tool }),
  });
}


export async function runSqlLab(
  input: string,
  tool:
    | "query-analyzer"
    | "schema-inspector"
    | "sql-formatter"
    | "data-profiler"
    | "security-analyzer"
    | "parameterization-coach"
    | "query-risk-report"
) {
  return apiRequest<unknown>("/tools/sql-lab", {
    method: "POST",
    body: JSON.stringify({ input, tool }),
  });
}

export async function runDataLab(
  input: string,
  tool:
    | "csv-analyzer"
    | "json-inspector"
    | "data-cleaner"
    | "pattern-analysis"
    | "log-analyzer"
    | "http-inspector"
    | "encoding-studio"
    | "hash-inspector"
    | "jwt-inspector"
    | "regex-lab"
    | "data-diff"
    | "sensitive-data-redactor"
) {
  return apiRequest<unknown>("/tools/data-lab", {
    method: "POST",
    body: JSON.stringify({ input, tool }),
  });
}


export async function runReconAnalysis(
  targetId: string,
  tool:
    | "asset-discovery"
    | "technology-detection"
    | "metadata-inspector"
    | "whois"
) {
  return apiRequest<unknown>("/tools/recon", {
    method: "POST",
    body: JSON.stringify({
      target_id: targetId,
      tool,
    }),
  });
}


export async function runNetworkAnalysis(
  targetId: string,
  tool:
    | "port-analysis"
    | "service-discovery"
    | "network-inspector"
    | "exposure-review"
) {
  return apiRequest<unknown>("/tools/network-analysis", {
    method: "POST",
    body: JSON.stringify({
      target_id: targetId,
      tool,
    }),
  });
}

export type DashboardRiskSummary = {
  score: number;
  level: string;
  highest: number;
  average: number;
  item_count: number;
};

export type DashboardTargetRisk = {
  target_id: string;
  name: string;
  hostname: string;
  status: string;
  risk_score: number;
  risk_level: string;
  finding_count: number;
};

export type DashboardRecentAssessment = {
  assessment_id: string;
  target?: {
    id: string;
    name: string;
    hostname: string;
  } | null;
  status: string;
  progress: number;
  risk_score: number;
  risk_level: string;
  finding_count: number;
  created_at?: string;
};

export type DashboardSummaryResponse = {
  success: boolean;
  data: {
    overall_risk: DashboardRiskSummary;
    targets: {
      total: number;
      active: number;
      risk: DashboardTargetRisk[];
    };
    assessments: {
      total: number;
      queued: number;
      running: number;
      completed: number;
      failed: number;
    };
    findings: {
      total: number;
      current: number;
      status: {
        open: number;
        confirmed: number;
        reopened: number;
        resolved: number;
      };
      risk_distribution: {
        critical: number;
        high: number;
        medium: number;
        low: number;
        informational: number;
      };
      critical_or_high: number;
    };
    recent_assessments: DashboardRecentAssessment[];
    scoring: {
      version: string;
      formula: string;
      source: string;
    };
  };
};

export async function getDashboardSummary(): Promise<DashboardSummaryResponse> {
  return apiRequest<DashboardSummaryResponse>("/dashboard");
}


export type AdminUserCreateInput = {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  role: string;
};

export type AdminUserUpdateInput = {
  name?: string;
  email?: string;
  password?: string | null;
  password_confirmation?: string;
};

export async function createAdminUser(
  payload: AdminUserCreateInput,
): Promise<{ data: ApiUser }> {
  return apiRequest<{ data: ApiUser }>("/users", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function updateAdminUser(
  userId: string,
  payload: AdminUserUpdateInput,
): Promise<{ data: ApiUser }> {
  return apiRequest<{ data: ApiUser }>(`/users/${userId}`, {
    method: "PATCH",
    body: JSON.stringify(payload),
  });
}

export async function updateAdminUserRole(
  userId: string,
  role: string,
): Promise<{ data: ApiUser }> {
  return apiRequest<{ data: ApiUser }>(`/users/${userId}/role`, {
    method: "PATCH",
    body: JSON.stringify({ role }),
  });
}


export async function updateAdminUserVerification(
  userId: string,
  verified: boolean,
): Promise<{ data: ApiUser }> {
  return apiRequest<{ data: ApiUser }>(
    `/users/${userId}/verification`,
    {
      method: "PATCH",
      body: JSON.stringify({ verified }),
    },
  );
}

export async function deleteAdminUser(
  userId: string,
): Promise<{
  success: boolean;
  message: string;
}> {
  return apiRequest<{
    success: boolean;
    message: string;
  }>(`/users/${userId}`, {
    method: "DELETE",
  });
}

export type AdminTargetOwner = {
  id: string;
  name: string;
  email: string;
};

export type AdminTarget = {
  id: string;
  user_id: string;

  name: string;
  url: string;
  hostname: string;
  scheme: string;
  port: number | null;

  authorization_confirmed: boolean;
  authorization_confirmed_at: string | null;
  authorization_method: string | null;

  status: string;

  owner: AdminTargetOwner | null;

  assessments_count: number;
  findings_count: number;
  last_assessment_at: string | null;

  created_at: string;
  updated_at: string;
};

export type AdminTargetsResponse = {
  success: boolean;

  data: {
    current_page: number;
    data: AdminTarget[];
    first_page_url?: string | null;
    from?: number | null;
    last_page: number;
    last_page_url?: string | null;
    links?: unknown[];
    next_page_url?: string | null;
    path?: string;
    per_page: number;
    prev_page_url?: string | null;
    to?: number | null;
    total: number;
  };
};

export async function getAdminTargets(
  params: {
    page?: number;
    per_page?: number;
  } = {},
): Promise<AdminTargetsResponse> {
  const search = new URLSearchParams();

  if (params.page) {
    search.set("page", String(params.page));
  }

  if (params.per_page) {
    search.set(
      "per_page",
      String(params.per_page),
    );
  }

  const suffix =
    search.toString()
      ? `?${search.toString()}`
      : "";

  return apiRequest<AdminTargetsResponse>(
    `/admin/targets${suffix}`,
  );
}

export async function updateAdminTarget(
  targetId: string,
  payload: {
    name?: string;
    status?: "active" | "paused";
    authorization_confirmed?: boolean;
  },
): Promise<{
  success: boolean;
  message: string;
  data: AdminTarget;
}> {
  return apiRequest<{
    success: boolean;
    message: string;
    data: AdminTarget;
  }>(
    `/admin/targets/${targetId}`,
    {
      method: "PATCH",
      body: JSON.stringify(payload),
    },
  );
}

export type AdminAssessmentOwner = {
  id: string;
  name: string;
  email: string;
};

export type AdminAssessmentTarget = {
  id: string;
  name: string;
  url: string;
  hostname: string;
  authorization_confirmed: boolean;
  status: string;
};

export type AdminAssessment = {
  id: string;
  user_id: string;
  target_id: string;

  profile: string;
  status: string;
  progress: number;

  worker_id: string | null;

  queued_at: string | null;
  started_at: string | null;
  completed_at: string | null;

  error_message: string | null;

  configuration: Record<string, unknown> | null;
  execution_metadata: Record<string, unknown> | null;

  findings_count: number;

  owner: AdminAssessmentOwner | null;
  target: AdminAssessmentTarget | null;

  created_at: string;
  updated_at: string;
};

export type AdminAssessmentsResponse = {
  success: boolean;

  data: {
    current_page: number;
    data: AdminAssessment[];
    last_page: number;
    per_page: number;
    total: number;
    next_page_url?: string | null;
    prev_page_url?: string | null;
  };
};

export async function getAdminAssessments(
  params: {
    page?: number;
    per_page?: number;
  } = {},
): Promise<AdminAssessmentsResponse> {
  const search = new URLSearchParams();

  if (params.page) {
    search.set("page", String(params.page));
  }

  if (params.per_page) {
    search.set("per_page", String(params.per_page));
  }

  const suffix = search.toString()
    ? `?${search.toString()}`
    : "";

  return apiRequest<AdminAssessmentsResponse>(
    `/admin/assessments${suffix}`,
  );
}

export type AdminFindingRisk = {
  score: number;
  level: string;
  components: {
    severity: {
      value: string;
      score: number;
      max: number;
    };
    confidence: {
      value: string;
      score: number;
      max: number;
    };
    recurrence: {
      occurrence_count: number;
      score: number;
      max: number;
    };
    lifecycle: {
      status: string;
      score: number;
      max: number;
    };
    asset_importance: {
      value: string;
      score: number;
      max: number;
    };
  };
};

export type AdminFindingLifecycle = {
  id: string;
  target_id: string;
  fingerprint: string;

  type: string;
  title: string;
  severity: string;
  confidence: string;
  status: string;

  occurrence_count: number;

  first_seen_at: string | null;
  last_seen_at: string | null;
  resolved_at: string | null;
  reopened_at: string | null;

  risk: AdminFindingRisk;

  owner: {
    id: string;
    name: string;
    email: string;
  } | null;

  target: {
    id: string;
    name: string;
    url: string;
    hostname: string;
    status: string;
    authorization_confirmed: boolean;
  } | null;

  first_assessment: {
    id: string;
    profile: string;
    status: string;
    created_at: string | null;
    completed_at: string | null;
  } | null;

  last_assessment: {
    id: string;
    profile: string;
    status: string;
    progress: number;
    worker_id: string | null;
    created_at: string | null;
    completed_at: string | null;
  } | null;

  latest_finding: {
    id: string;
    assessment_id: string;
    description: string | null;
    evidence: string | null;
    evidence_data: Record<string, unknown> | null;
    remediation: string | null;
    created_at: string | null;
    updated_at: string | null;
  } | null;

  created_at: string;
  updated_at: string;
};

export type AdminFindingLifecyclesResponse = {
  success: boolean;
  data: {
    current_page: number;
    data: AdminFindingLifecycle[];
    last_page: number;
    per_page: number;
    total: number;
    next_page_url?: string | null;
    prev_page_url?: string | null;
  };
};

export async function getAdminFindingLifecycles(
  params: {
    page?: number;
    per_page?: number;
  } = {},
): Promise<AdminFindingLifecyclesResponse> {
  const search = new URLSearchParams();

  if (params.page) {
    search.set("page", String(params.page));
  }

  if (params.per_page) {
    search.set("per_page", String(params.per_page));
  }

  const suffix = search.toString()
    ? `?${search.toString()}`
    : "";

  return apiRequest<AdminFindingLifecyclesResponse>(
    `/admin/finding-lifecycles${suffix}`,
  );
}

export async function updateAdminFindingLifecycleStatus(
  lifecycleId: string,
  status: "confirmed" | "resolved",
): Promise<{
  success: boolean;
  message: string;
  data: AdminFindingLifecycle;
}> {
  return apiRequest(
    `/admin/finding-lifecycles/${lifecycleId}/status`,
    {
      method: "PATCH",
      body: JSON.stringify({ status }),
    },
  );
}

export type AdminWorkerOwner = {
  id: string;
  name: string;
  email: string;
};

export type AdminWorkerTarget = {
  id: string;
  name: string;
  url: string;
  hostname: string;
};

export type AdminRunningExecution = {
  assessment_id: string;
  worker_id: string | null;
  profile: string;
  progress: number;
  started_at: string | null;
  queued_at: string | null;
  owner: AdminWorkerOwner | null;
  target: AdminWorkerTarget | null;
};

export type AdminQueuedAssessment = {
  assessment_id: string;
  profile: string;
  progress: number;
  queued_at: string | null;
  owner: AdminWorkerOwner | null;
  target: AdminWorkerTarget | null;
};

export type AdminFailedAssessment = {
  assessment_id: string;
  status: string;
  profile: string;
  worker_id: string | null;
  error_message: string | null;
  started_at: string | null;
  completed_at: string | null;
  owner: AdminWorkerOwner | null;
  target: AdminWorkerTarget | null;
};

export type AdminFailedQueueJob = {
  id: number;
  uuid: string;
  connection: string;
  queue: string;
  failed_at: string;
};

export type AdminWorkerNodeHealth =
  | "healthy"
  | "stale"
  | "stopped";

export type AdminWorkerNode = {
  id: string;
  worker_type: string;
  hostname: string | null;
  pid: number | null;
  connection: string | null;
  queue: string | null;
  status: string;
  health: AdminWorkerNodeHealth;
  started_at: string | null;
  last_heartbeat_at: string | null;
  heartbeat_age_seconds: number | null;
  stopped_at: string | null;
  metadata: Record<string, unknown> | null;
};

export type AdminWorkerRegistry = {
  observable: boolean;
  semantics: "application_heartbeat";
  stale_after_seconds: number;
  summary_status:
    | "healthy"
    | "degraded"
    | "stale"
    | "no_active_workers"
    | "unavailable";
  healthy: number;
  stale: number;
  stopped: number;
  total: number;
  nodes: AdminWorkerNode[];
};

export type AdminWorkersResponse = {
  success: boolean;
  data: {
    queue: {
      driver: string | null;
      connection_status: string;
      depth_observable: boolean;
      queue_name: string | null;
      total_jobs: number | null;
      ready_jobs: number | null;
      reserved_jobs: number | null;
      delayed_jobs: number | null;
      oldest_job_at: string | null;
      note: string;
    };

    failed_jobs: {
      driver: string | null;
      observable: boolean;
      total: number | null;
      recent: AdminFailedQueueJob[];
    };

    scanner: {
      running_assessments: number;
      queued_assessments: number;
      observed_execution_ids: number;
      worker_process_health: string;
      worker_process_health_note: string;
      healthy_workers: number;
      stale_workers: number;
      stopped_workers: number;
    };

    worker_registry: AdminWorkerRegistry;
    running_executions: AdminRunningExecution[];
    queued_assessments: AdminQueuedAssessment[];
    recent_failed_assessments: AdminFailedAssessment[];
    generated_at: string;
  };
};

export async function getAdminWorkers(): Promise<AdminWorkersResponse> {
  return apiRequest<AdminWorkersResponse>(
    "/admin/workers",
  );
}

export type AdminTelemetryUser = {
  id: string;
  name: string;
  email: string;
};

export type AdminAuditLog = {
  id: string;
  action: string;
  category: string | null;
  method: string | null;
  route: string | null;
  ip_address: string | null;
  user_agent: string | null;
  resource_type: string | null;
  resource_id: string | null;
  user: AdminTelemetryUser | null;
  metadata: Record<string, unknown> | null;
  created_at: string;
};

export type AdminActivityEvent = {
  id: string;
  event_type: string;
  ip_address: string | null;
  country_code: string | null;
  country_name: string | null;
  region: string | null;
  city: string | null;
  device_type: string | null;
  browser: string | null;
  platform: string | null;
  user: AdminTelemetryUser | null;
  metadata: Record<string, unknown> | null;
  created_at: string;
};

export type AdminTelemetryResponse = {
  success: boolean;
  data: {
    audit_logs: AdminAuditLog[];
    activity_events: AdminActivityEvent[];
    summary: {
      audit_logs_returned: number;
      activity_events_returned: number;
      limit: number;
    };
    generated_at: string;
  };
};

export async function getAdminTelemetry(
  limit = 100,
): Promise<AdminTelemetryResponse> {
  return apiRequest<AdminTelemetryResponse>(
    `/admin/telemetry?limit=${encodeURIComponent(String(limit))}`,
  );
}

export type AdminSecurityUser = {
  id: string;
  name: string;
  email: string;
};

export type AdminSecurityDeviceStatus =
  | "trusted"
  | "pending"
  | "expired"
  | "revoked";

export type AdminSecurityDevice = {
  id: string;
  name: string | null;
  device_type: string | null;
  browser: string | null;
  platform: string | null;
  last_ip_address: string | null;

  first_seen_at: string | null;
  last_seen_at: string | null;
  verified_at: string | null;
  expires_at: string | null;
  revoked_at: string | null;

  is_trusted: boolean;
  status: AdminSecurityDeviceStatus;

  user: AdminSecurityUser | null;
  revoked_by: AdminSecurityUser | null;
};

export type AdminSecurityResponse = {
  success: boolean;
  data: {
    devices: AdminSecurityDevice[];

    summary: {
      total: number;
      trusted: number;
      pending: number;
      expired: number;
      revoked: number;
    };

    capabilities: {
      registry: boolean;
      verification: boolean;
      revocation: boolean;
      admin_access_enforcement: boolean;
      session_termination_on_revoke: boolean;
      mfa_or_passkey_verification: boolean;
    };

    generated_at: string;
  };
};

export async function getAdminSecurity(): Promise<AdminSecurityResponse> {
  return apiRequest<AdminSecurityResponse>("/admin/security");
}

export async function revokeAdminSecurityDevice(
  deviceId: string,
): Promise<{
  success: boolean;
  message: string;
  data?: {
    device: AdminSecurityDevice;
  };
}> {
  return apiRequest(
    `/admin/security/devices/${encodeURIComponent(deviceId)}/revoke`,
    {
      method: "POST",
    },
  );
}

export type AdminPlatformSettings = {
  public_registration_enabled: boolean;
  assessment_creation_enabled: boolean;
  trusted_device_admin_enforcement: boolean;
};

export type AdminSettingsCapabilities = {
  public_registration_enforcement: boolean;
  assessment_creation_enforcement: boolean;
  mfa_enforcement: boolean;
  trusted_device_admin_enforcement: boolean;
  session_termination_on_device_revoke: boolean;
  worker_resource_limits: boolean;
  worker_egress_policy: boolean;
  worker_isolation: boolean;
  retention_cleanup: boolean;
};

export type AdminSettingsResponse = {
  success: boolean;
  data: {
    settings: AdminPlatformSettings;
    capabilities: AdminSettingsCapabilities;
    generated_at: string;
  };
};

export async function getAdminSettings(): Promise<AdminSettingsResponse> {
  return apiRequest<AdminSettingsResponse>("/admin/settings");
}

export async function updateAdminSettings(
  settings: Partial<AdminPlatformSettings>,
): Promise<{
  success: boolean;
  message: string;
  data: {
    settings: AdminPlatformSettings;
  };
}> {
  return apiRequest("/admin/settings", {
    method: "PATCH",
    body: JSON.stringify(settings),
  });
}


export type AdminRolePermission = {
  id: string;
  name: string;
  slug: string;
  group: string;
  description: string | null;
};

export type AdminRoleRecord = {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  is_system: boolean;
  permissions: AdminRolePermission[];
};

export type AdminRolesResponse = {
  success: boolean;
  data: {
    roles: AdminRoleRecord[];
    permissions: AdminRolePermission[];
    summary: {
      roles: number;
      system_roles: number;
      permissions: number;
      permission_groups: number;
    };
    capabilities: {
      role_registry: boolean;
      permission_registry: boolean;
      role_mutation: boolean;
      permission_mutation: boolean;
    };
    generated_at: string;
  };
};

export async function getAdminRoles(): Promise<AdminRolesResponse> {
  return apiRequest<AdminRolesResponse>("/admin/roles");
}

/* -------------------------------------------------------------------------- */
/* Reports V1                                                                 */
/* -------------------------------------------------------------------------- */

export type ReportTargetSnapshot = {
  id: string;
  name: string | null;
  url: string | null;
  hostname: string | null;
  scheme: string | null;
  port: number | null;
  status: string;
  authorization: {
    confirmed: boolean;
    confirmed_at: string | null;
    method: string | null;
  };
};

export type ReportAssessmentSnapshot = {
  id: string;
  target_id: string;
  profile: string;
  status: string;
  progress: number;
  queued_at: string | null;
  started_at: string | null;
  completed_at: string | null;
  configuration: Record<string, unknown> | null;
  execution_metadata: Record<string, unknown> | null;
  finding_count: number;
};

export type ReportFindingSnapshot = {
  id: string;
  fingerprint: string | null;
  type: string;
  title: string;
  description: string | null;
  severity: string;
  confidence: string;
  evidence: string | null;
  evidence_data: Record<string, unknown> | null;
  remediation: string | null;
  status: string;
};

export type ReportRiskSnapshot = AssessmentRiskAggregate & {
  scoring: {
    source: string;
    probability: false;
    immutable_snapshot: true;
    finding_count?: number;
    scored_lifecycle_count?: number;
  };
};

export type ReportMetadata = {
  schema_version: string;
  generated_at: string;
  immutable_snapshot: true;
  source: string;
  risk_source: string;
  risk_probability: false;
  intelligence_historical_snapshot: false;
};

export type ReportRecord = {
  id: string;
  user_id: string;
  target_id: string;
  assessment_id: string;

  title: string;
  status: "ready" | string;

  target_snapshot: ReportTargetSnapshot;
  assessment_snapshot: ReportAssessmentSnapshot;
  findings_snapshot: ReportFindingSnapshot[];
  risk_snapshot: ReportRiskSnapshot;

  intelligence_snapshot:
    | AssessmentIntelligenceResponse["data"]
    | null;

  metadata: ReportMetadata;

  generated_at: string;
  created_at: string;
  updated_at: string;

  target?: {
    id: string;
    name: string | null;
    url: string | null;
    hostname: string | null;
  };

  assessment?: {
    id: string;
    target_id: string;
    profile: string;
    status: string;
    completed_at: string | null;
  };
};

export type ReportsListResponse = {
  success: boolean;
  data: {
    current_page: number;
    data: ReportRecord[];
    first_page_url?: string | null;
    from?: number | null;
    last_page: number;
    last_page_url?: string | null;
    links?: Array<{
      url: string | null;
      label: string;
      active: boolean;
    }>;
    next_page_url?: string | null;
    path?: string;
    per_page: number;
    prev_page_url?: string | null;
    to?: number | null;
    total: number;
  };
};

export type ReportDetailResponse = {
  success: boolean;
  data: ReportRecord;
};

export type GenerateReportResponse = {
  success: boolean;
  message: string;
  data: ReportRecord;
};

export async function getReports(): Promise<ReportsListResponse> {
  return apiRequest<ReportsListResponse>("/reports");
}

export async function getReport(
  reportId: string,
): Promise<ReportDetailResponse> {
  return apiRequest<ReportDetailResponse>(
    `/reports/${encodeURIComponent(reportId)}`,
  );
}

export async function generateReport(
  assessmentId: string,
): Promise<GenerateReportResponse> {
  return apiRequest<GenerateReportResponse>(
    `/assessments/${encodeURIComponent(assessmentId)}/reports`,
    {
      method: "POST",
    },
  );
}

/* -------------------------------------------------------------------------- */
/* Admin Reports V1                                                           */
/* -------------------------------------------------------------------------- */

export type AdminReportOwner = {
  id: string;
  name: string;
  email: string;
};

export type AdminReportTarget = {
  id: string;
  name: string | null;
  url: string | null;
  hostname: string | null;
  status: string;
  authorization_confirmed: boolean;
};

export type AdminReportAssessment = {
  id: string;
  profile: string;
  status: string;
  completed_at: string | null;
};

export type AdminReportSummary = {
  findings_count: number;
  risk_score: number | null;
  risk_level: string | null;
  immutable_snapshot: boolean;
};

export type AdminReportRecord = {
  id: string;
  user_id: string;
  target_id: string;
  assessment_id: string;
  title: string;
  status: string;

  owner: AdminReportOwner | null;
  target: AdminReportTarget | null;
  assessment: AdminReportAssessment | null;

  target_snapshot: ReportTargetSnapshot;
  assessment_snapshot: ReportAssessmentSnapshot;
  findings_snapshot: ReportFindingSnapshot[];
  risk_snapshot: ReportRiskSnapshot;
  intelligence_snapshot:
    | AssessmentIntelligenceResponse["data"]
    | null;
  metadata: ReportMetadata;

  summary: AdminReportSummary;

  generated_at: string;
  created_at: string;
  updated_at: string;
};

export type AdminReportsResponse = {
  success: boolean;
  data: {
    current_page: number;
    data: AdminReportRecord[];
    first_page_url?: string | null;
    from?: number | null;
    last_page: number;
    last_page_url?: string | null;
    links?: Array<{
      url: string | null;
      label: string;
      active: boolean;
    }>;
    next_page_url?: string | null;
    path?: string;
    per_page: number;
    prev_page_url?: string | null;
    to?: number | null;
    total: number;
  };
};

export type AdminReportDetailResponse = {
  success: boolean;
  data: AdminReportRecord;
};

export async function getAdminReports(
  perPage = 50,
): Promise<AdminReportsResponse> {
  return apiRequest<AdminReportsResponse>(
    `/admin/reports?per_page=${encodeURIComponent(String(perPage))}`,
  );
}

export async function getAdminReport(
  reportId: string,
): Promise<AdminReportDetailResponse> {
  return apiRequest<AdminReportDetailResponse>(
    `/admin/reports/${encodeURIComponent(reportId)}`,
  );
}

/* ==========================================================================
 * Monitoring
 * ========================================================================== */

export type MonitoringProfile = "discovery" | "standard" | "deep";

export type MonitoringAssessmentSummary = {
  id: string;
  target_id: string;
  profile: string;
  status: string;
  queued_at: string | null;
  started_at: string | null;
  completed_at: string | null;
};

export type MonitoringTargetSummary = {
  id: string;
  name: string;
  url: string;
  hostname: string;
  status: string;
  authorization_confirmed: boolean;
};

export type MonitoringPolicy = {
  id: string;
  user_id: string;
  target_id: string;
  enabled: boolean;
  profile: MonitoringProfile;
  interval_minutes: number;
  configuration: Record<string, unknown> | unknown[];
  last_scheduled_at: string | null;
  next_run_at: string | null;
  last_assessment_id: string | null;
  created_at: string;
  updated_at: string;
  target?: MonitoringTargetSummary | null;
  last_assessment?: MonitoringAssessmentSummary | null;
};

export type MonitoringPoliciesResponse = {
  success: boolean;
  data: MonitoringPolicy[];
};

export type MonitoringPolicyResponse = {
  success: boolean;
  message?: string;
  data: MonitoringPolicy | null;
};

export async function getMonitoringPolicies(): Promise<MonitoringPoliciesResponse> {
  return apiRequest<MonitoringPoliciesResponse>("/monitoring");
}

export async function getTargetMonitoringPolicy(
  targetId: string,
): Promise<MonitoringPolicyResponse> {
  return apiRequest<MonitoringPolicyResponse>(
    `/targets/${encodeURIComponent(targetId)}/monitoring`,
  );
}

export async function upsertMonitoringPolicy(
  targetId: string,
  input: {
    enabled?: boolean;
    profile?: MonitoringProfile;
    interval_minutes?: number;
    configuration?: Record<string, unknown>;
  },
): Promise<MonitoringPolicyResponse> {
  return apiRequest<MonitoringPolicyResponse>(
    `/targets/${encodeURIComponent(targetId)}/monitoring`,
    {
      method: "PUT",
      body: JSON.stringify(input),
    },
  );
}

export async function disableMonitoringPolicy(
  targetId: string,
): Promise<MonitoringPolicyResponse> {
  return apiRequest<MonitoringPolicyResponse>(
    `/targets/${encodeURIComponent(targetId)}/monitoring`,
    {
      method: "DELETE",
    },
  );
}

export type MonitoringNotificationEventType =
  | "finding_new"
  | "finding_reappeared"
  | "finding_no_longer_detected"
  | "finding_reopened"
  | "risk_changed";

export type MonitoringNotificationPreference = {
  persisted: boolean;
  id?: string;
  email_enabled: boolean;
  event_types: MonitoringNotificationEventType[];
  minimum_risk_delta: number;
  created_at?: string | null;
  updated_at?: string | null;
};

export type MonitoringNotificationPreferenceResponse = {
  success: boolean;
  message?: string;
  data: MonitoringNotificationPreference;
};

export async function getMonitoringNotificationPreferences(): Promise<MonitoringNotificationPreferenceResponse> {
  return apiRequest<MonitoringNotificationPreferenceResponse>(
    "/monitoring/notifications/preferences",
  );
}

export async function updateMonitoringNotificationPreferences(input: {
  email_enabled?: boolean;
  event_types?: MonitoringNotificationEventType[];
  minimum_risk_delta?: number;
}): Promise<MonitoringNotificationPreferenceResponse> {
  return apiRequest<MonitoringNotificationPreferenceResponse>(
    "/monitoring/notifications/preferences",
    {
      method: "PUT",
      body: JSON.stringify(input),
    },
  );
}


/* ==========================================================================
 * Monitoring History
 * ========================================================================== */

export type MonitoringHistoryAssessment = {
  id: string;
  profile: string;
  status: string;
  queued_at?: string | null;
  started_at?: string | null;
  completed_at: string | null;
};

export type MonitoringHistoryTarget = {
  id: string;
  name: string;
  url: string;
  hostname: string;
};

export type MonitoringChangeEvent = {
  id: string;
  event_type: MonitoringNotificationEventType;
  fingerprint: string | null;
  payload: Record<string, unknown> | null;
  detected_at: string | null;
  target: MonitoringHistoryTarget | null;
  assessment: MonitoringHistoryAssessment | null;
  previous_assessment: MonitoringHistoryAssessment | null;
};

export type MonitoringEventsResponse = {
  success: boolean;
  data: {
    events: MonitoringChangeEvent[];
    generated_at: string;
  };
};

export type MonitoringDelivery = {
  id: string;
  change_event_id: string;
  channel: string;
  status: string;
  recipient: string;
  attempt_count: number;
  processing_at: string | null;
  sent_at: string | null;
  failed_at: string | null;
  failure_class: string | null;
  created_at: string | null;
  event: {
    id: string;
    event_type: MonitoringNotificationEventType;
    fingerprint: string | null;
    detected_at: string | null;
    target: MonitoringHistoryTarget | null;
  } | null;
};

export type MonitoringDeliveriesResponse = {
  success: boolean;
  data: {
    deliveries: MonitoringDelivery[];
    generated_at: string;
  };
};

export type MonitoringEventDetailResponse = {
  success: boolean;
  data: {
    event: MonitoringChangeEvent;
    deliveries: Array<{
      id: string;
      channel: string;
      status: string;
      recipient: string;
      attempt_count: number;
      processing_at: string | null;
      sent_at: string | null;
      failed_at: string | null;
      failure_class: string | null;
      created_at: string | null;
    }>;
    semantics: {
      absence_means_resolved: boolean;
      no_longer_detected_is_observation: boolean;
    };
  };
};

export async function getMonitoringEvents(): Promise<MonitoringEventsResponse> {
  return apiRequest<MonitoringEventsResponse>("/monitoring/events");
}

export async function getMonitoringEvent(
  eventId: string,
): Promise<MonitoringEventDetailResponse> {
  return apiRequest<MonitoringEventDetailResponse>(
    `/monitoring/events/${encodeURIComponent(eventId)}`,
  );
}

export async function getMonitoringDeliveries(): Promise<MonitoringDeliveriesResponse> {
  return apiRequest<MonitoringDeliveriesResponse>(
    "/monitoring/deliveries",
  );
}

/* ==========================================================================
 * Admin Monitoring
 * ========================================================================== */

export type AdminMonitoringOwner = {
  id: string;
  name: string;
  email: string;
};

export type AdminMonitoringTarget = {
  id: string;
  name: string;
  url: string;
  hostname: string;
  status?: string;
  authorization_confirmed?: boolean;
};

export type AdminMonitoringPolicyItem = {
  id: string;
  user_id: string;
  target_id: string;
  enabled: boolean;
  profile: string;
  interval_minutes: number;
  last_scheduled_at: string | null;
  next_run_at: string | null;
  owner: AdminMonitoringOwner | null;
  target: AdminMonitoringTarget | null;
  last_assessment: {
    id: string;
    profile: string;
    status: string;
    queued_at: string | null;
    started_at: string | null;
    completed_at: string | null;
  } | null;
};

export type AdminMonitoringSummary = {
  total_policies: number;
  active_policies: number;
  disabled_policies: number;
  due_policies: number;
  change_events: number;
  pending_deliveries: number;
  processing_deliveries: number;
  sent_deliveries: number;
  failed_deliveries: number;
};

export type AdminMonitoringResponse = {
  success: boolean;
  data: {
    summary: AdminMonitoringSummary;
    policies: AdminMonitoringPolicyItem[];
    generated_at: string;
  };
};

export type AdminMonitoringEvent = {
  id: string;
  event_type: string;
  fingerprint: string | null;
  detected_at: string | null;
  payload: Record<string, unknown> | null;
  owner: AdminMonitoringOwner | null;
  target: AdminMonitoringTarget | null;
  assessment: {
    id: string;
    profile: string;
    status: string;
    completed_at: string | null;
  } | null;
  previous_assessment: {
    id: string;
    profile: string;
    status: string;
    completed_at: string | null;
  } | null;
};

export type AdminMonitoringEventsResponse = {
  success: boolean;
  data: {
    events: AdminMonitoringEvent[];
    generated_at: string;
  };
};

export type AdminMonitoringDelivery = {
  id: string;
  change_event_id: string;
  channel: string;
  status: string;
  recipient: string;
  attempt_count: number;
  processing_at: string | null;
  sent_at: string | null;
  failed_at: string | null;
  failure_class: string | null;
  created_at: string | null;
  owner: AdminMonitoringOwner | null;
  event: {
    id: string;
    event_type: string;
    fingerprint: string | null;
    detected_at: string | null;
    target: AdminMonitoringTarget | null;
  } | null;
};

export type AdminMonitoringDeliveriesResponse = {
  success: boolean;
  data: {
    deliveries: AdminMonitoringDelivery[];
    generated_at: string;
  };
};

export async function getAdminMonitoring(): Promise<AdminMonitoringResponse> {
  return apiRequest<AdminMonitoringResponse>("/admin/monitoring");
}

export async function getAdminMonitoringEvents(): Promise<AdminMonitoringEventsResponse> {
  return apiRequest<AdminMonitoringEventsResponse>("/admin/monitoring/events");
}

export async function getAdminMonitoringDeliveries(): Promise<AdminMonitoringDeliveriesResponse> {
  return apiRequest<AdminMonitoringDeliveriesResponse>(
    "/admin/monitoring/deliveries",
  );
}

export type PremiumPlanCapabilities = {
  core_tools: boolean;
  security_labs: boolean;
  target_management: boolean;
  assessments: boolean;
  reports: boolean;
  monitoring: boolean;
  priority_processing: boolean;
  team_workspace: boolean;
  shared_targets: boolean;
  team_rbac: boolean;
  team_audit_activity: boolean;
  [key: string]: boolean;
};

export type PremiumPlanLimits = {
  targets_total: number | null;
  assessments_monthly: number | null;
  reports_monthly: number | null;
  monitoring_policies: number | null;
  concurrent_assessments: number | null;
  [key: string]: number | null;
};

export type PremiumPlan = {
  code: string;
  name: string;
  price_monthly_usd: number;
  capabilities: PremiumPlanCapabilities;
  limits: PremiumPlanLimits;
};

export type PlanCatalogResponse = {
  success: boolean;
  data: Record<string, PremiumPlan>;
};

export type AccountUsage = {
  targets_total: number;
  assessments_monthly: number;
  reports_monthly: number;
  monitoring_policies: number;
  concurrent_assessments: number;
};

export type PlanEntitlements = {
  plan: {
    code: string;
    name: string;
  };
  capabilities: PremiumPlanCapabilities;
  limits: PremiumPlanLimits;
};

export type AccountEntitlements = PlanEntitlements & {
  usage: AccountUsage;
};

export type AccountSubscriptionState = {
  subscribed: boolean;
  plan_code: string | null;
  status: string | null;
  provider: string | null;
  current_period_start: string | null;
  current_period_end: string | null;
  cancel_at_period_end: boolean;
};

export type AccountSubscriptionResponse = {
  success: boolean;
  data: {
    subscription: AccountSubscriptionState;
    effective_entitlements: PlanEntitlements;
  };
};

export type AccountEntitlementsResponse = {
  success: boolean;
  data: AccountEntitlements;
};

export async function getPlanCatalog(): Promise<PlanCatalogResponse> {
  return apiRequest<PlanCatalogResponse>("/plans");
}

export async function getAccountSubscription(): Promise<AccountSubscriptionResponse> {
  return apiRequest<AccountSubscriptionResponse>(
    "/account/subscription",
  );
}

export async function getAccountEntitlements(): Promise<AccountEntitlementsResponse> {
  return apiRequest<AccountEntitlementsResponse>(
    "/account/entitlements",
  );
}

export type BillingStatus = {
  configured: boolean;
  checkout_enabled: boolean;
  provider: string | null;
};

export type BillingStatusResponse = {
  success: boolean;
  data: BillingStatus;
};

export type BillingCheckoutResponse = {
  success: boolean;
  data: {
    transaction_id: string;
    provider: string;
  };
};

export async function getBillingStatus(): Promise<BillingStatusResponse> {
  return apiRequest<BillingStatusResponse>(
    "/billing/status",
  );
}

export async function createBillingCheckout(
  planCode: "professional" | "team",
): Promise<BillingCheckoutResponse> {
  return apiRequest<BillingCheckoutResponse>(
    "/account/billing/checkout",
    {
      method: "POST",
      body: JSON.stringify({
        plan_code: planCode,
      }),
    },
  );
}

/* -------------------------------------------------------------------------- */
/* WebAuthn / Passkey enrollment                                              */
/* -------------------------------------------------------------------------- */

type PasskeyRegistrationOptionsResponse = {
  success: boolean;
  data: {
    transaction_id: string;
    public_key: {
      challenge: string;
      rp: {
        id?: string;
        name: string;
      };
      user: {
        id: string;
        name: string;
        displayName: string;
      };
      pubKeyCredParams: PublicKeyCredentialParameters[];
      timeout?: number;
      excludeCredentials?: Array<{
        type: PublicKeyCredentialType;
        id: string;
        transports?: AuthenticatorTransport[];
      }>;
      authenticatorSelection?: AuthenticatorSelectionCriteria;
      attestation?: AttestationConveyancePreference;
    };
  };
};

type PasskeyRegistrationVerifyResponse = {
  success: boolean;
  message?: string;
  data?: {
    id?: string;
    name?: string | null;
  };
};

function base64UrlToArrayBuffer(value: string): ArrayBuffer {
  const normalized = value.replace(/-/g, "+").replace(/_/g, "/");
  const padded =
    normalized + "=".repeat((4 - (normalized.length % 4)) % 4);

  const binary = window.atob(padded);
  const bytes = new Uint8Array(binary.length);

  for (let index = 0; index < binary.length; index += 1) {
    bytes[index] = binary.charCodeAt(index);
  }

  return bytes.buffer;
}

function bytesToBase64Url(value: ArrayBuffer): string {
  const bytes = new Uint8Array(value);
  let binary = "";

  for (let index = 0; index < bytes.length; index += 1) {
    binary += String.fromCharCode(bytes[index]);
  }

  return window
    .btoa(binary)
    .replace(/\+/g, "-")
    .replace(/\//g, "_")
    .replace(/=+$/g, "");
}

export async function enrollPasskey(): Promise<PasskeyRegistrationVerifyResponse> {
  if (typeof window === "undefined") {
    throw new Error("Passkey enrollment requires a browser.");
  }

  if (
    !window.PublicKeyCredential ||
    typeof navigator.credentials?.create !== "function"
  ) {
    throw new Error(
      "Passkeys are not supported by this browser or device.",
    );
  }

  const optionsResponse =
    await apiRequest<PasskeyRegistrationOptionsResponse>(
      "/auth/passkey/register/options",
      {
        method: "POST",
      },
    );

  const serverOptions = optionsResponse.data.public_key;

  const publicKey: PublicKeyCredentialCreationOptions = {
    ...serverOptions,
    challenge: base64UrlToArrayBuffer(serverOptions.challenge),
    user: {
      ...serverOptions.user,
      id: base64UrlToArrayBuffer(serverOptions.user.id),
    },
    excludeCredentials: serverOptions.excludeCredentials?.map(
      (credential) => ({
        ...credential,
        id: base64UrlToArrayBuffer(credential.id),
      }),
    ),
  };

  const credential = await navigator.credentials.create({
    publicKey,
  });

  if (!(credential instanceof PublicKeyCredential)) {
    throw new Error("Passkey creation was cancelled or failed.");
  }

  const response = credential.response;

  if (!(response instanceof AuthenticatorAttestationResponse)) {
    throw new Error("Invalid passkey registration response.");
  }

  const transports =
    typeof response.getTransports === "function"
      ? response.getTransports()
      : [];

  return apiRequest<PasskeyRegistrationVerifyResponse>(
    "/auth/passkey/register/verify",
    {
      method: "POST",
      body: JSON.stringify({
        transaction_id: optionsResponse.data.transaction_id,
        credential: {
          id: credential.id,
          rawId: bytesToBase64Url(credential.rawId),
          type: credential.type,
          authenticatorAttachment:
            credential.authenticatorAttachment ?? null,
          response: {
            clientDataJSON: bytesToBase64Url(
              response.clientDataJSON,
            ),
            attestationObject: bytesToBase64Url(
              response.attestationObject,
            ),
            transports,
          },
          clientExtensionResults:
            credential.getClientExtensionResults(),
        },
      }),
    },
  );
}

export type PasskeyStatusCredential = {
  id: string;
  name: string | null;
  active: boolean;
  transports: AuthenticatorTransport[] | null;
  created_at: string | null;
  last_used_at: string | null;
  revoked_at: string | null;
};

export type PasskeyStatusResponse = {
  success: boolean;
  data: {
    configured: boolean;
    active_count: number;
    total_count: number;
    credentials: PasskeyStatusCredential[];
  };
};

export async function getPasskeyStatus(): Promise<PasskeyStatusResponse> {
  return apiRequest<PasskeyStatusResponse>("/auth/passkey/status", {
    method: "GET",
  });
}

export type UserDevice = {
  id: string;
  device_type: string | null;
  browser: string | null;
  platform: string | null;
  registered_at: string | null;
  last_seen_at: string | null;
  current: boolean;
};

export type UserDevicesResponse = {
  success: boolean;
  data: {
    devices: UserDevice[];
  };
};

export type RemoveUserDeviceResponse = {
  success: boolean;
  message: string;
  data: {
    current_device_removed: boolean;
  };
};

function getUserDeviceHeaders(): Headers {
  const headers = new Headers();

  if (typeof window !== "undefined") {
    const token = window.localStorage.getItem(
      "crypticx_user_device_token",
    );

    if (token) {
      headers.set("X-User-Device-Token", token);
    }
  }

  return headers;
}

export async function getUserDevices(): Promise<UserDevicesResponse> {
  return apiRequest<UserDevicesResponse>(
    "/account/devices",
    {
      headers: getUserDeviceHeaders(),
    },
  );
}

export async function removeUserDevice(
  deviceId: string,
): Promise<RemoveUserDeviceResponse> {
  const response = await apiRequest<RemoveUserDeviceResponse>(
    `/account/devices/${encodeURIComponent(deviceId)}`,
    {
      method: "DELETE",
      headers: getUserDeviceHeaders(),
    },
  );

  if (
    typeof window !== "undefined" &&
    response.data.current_device_removed
  ) {
    window.localStorage.removeItem(
      "crypticx_user_device_token",
    );
    window.localStorage.removeItem("crypticx_token");
    window.sessionStorage.removeItem("crypticx_token");
  }

  return response;
}
