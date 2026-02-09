import { Injectable } from '@angular/core';
import { ApiService } from './api.service';

// ===============================
// Tipos INLINE (MVP)
// ===============================
export type TrainingStatus = 'scheduled' | 'in_progress' | 'completed' | 'skipped' | 'cancelled';
export type TrainingSource = 'personal' | 'group' | 'free';
export type TrainingSectionResultType = 'number' | 'time' | 'text' | 'bool' | 'json';


export interface SaveSectionResultPayload {
  training_assignment_id: number;
    result_type: 'weight' | 'time' | 'reps' | 'distance' | string;

  value: string | number | boolean | any; // en tu app puedes mandar string y ya
  notes?: string | null;
}

export type TrainingSessionDTO = {
  id: number;
  coach_id: number;
  title: string;
  duration_minutes: number | null;
  level: string;
  goal: string | null;
  type: string | null;
  visibility: string | null;
  notes: string | null;
  

  // Placeholder (futuro: migración + payload)
  cover_image?: string | null;
};

export type TrainingFeedItemDTO = {
  assignment_id: number | null;
  source: TrainingSource;
  status: TrainingStatus;
  scheduled_for: string | null; // YYYY-MM-DD
  training_session: TrainingSessionDTO;

  group: null | { id: number; name: string };

  progress: {
    sections_total: number;
    sections_with_results: number;
    pct: number;
  };
};

export interface TrainingSectionDTO {
  id: number;
  name: string;
  description: string;
  video_url: string | null;
  order: number;

  accepts_results: boolean;

  // ✅ Tipo real: lo que el coach eligió
  result_type: 'number' | 'time' | 'text' | 'bool' | 'json' | null;

  completed: boolean;

  // ✅ Resultado real (si existe)
  result: {
    value: any;
    unit: string | null;
    notes: string | null;
    // mantenemos "completed_at" para NO tocar tu HTML,
    // pero lo vamos a llenar con recorded_at/updated_at del backend
    completed_at: string;
  } | null;
}

export interface TrainingDetailDTO {
  assignment_id: number | null;

  // show() actual no devuelve source; MVP fijo
  source: 'personal' | 'group' | 'free';

  status: TrainingStatus;
  scheduled_for: string;

  // show() actual no devuelve estos timestamps; MVP null
  started_at: string | null;
  completed_at: string | null;

  training_session: {
    id: number;
    coach_id: number;
    title: string;
    duration_minutes: number | null;
    level: string;
    goal: string | null;
    type: string | null;
    visibility: string | null;
    notes: string | null;

    cover_image?: string | null; // placeholder
  };

  // show() actual no devuelve group; MVP null
  group: { id: number; name: string } | null;

  progress: {
    sections_total: number;
    sections_completed: number;
    pct: number;
  };

  sections: TrainingSectionDTO[];
}

type TrainingsIndexResponse = {
  ok: boolean;
  data: TrainingFeedItemDTO[];
};

// ===============================
// Contrato REAL del backend show()
// ===============================
type TrainingSectionLatestResultDTO = {
  id: number;
  result_type: string | null;
  value: any;
  unit: string | null;
  notes: string | null;
  created_at: string; // ISO
};

type TrainingSectionShowDTO = {
  id: number;
  order: number;
  name: string;
  description: string;
  video_url: string | null; 
  accepts_results: boolean;
  unit_default: string | null;
  latest_result: TrainingSectionLatestResultDTO | null;
};

type TrainingAssignmentShowResponse = {
  ok: boolean;
  data: {
    assignment: {
      id: number;
      status: TrainingStatus;
      scheduled_for: string | null; // YYYY-MM-DD
    };
    training_session: TrainingSessionDTO | null;
    sections: TrainingSectionShowDTO[];
    progress: {
      sections_total: number;
      sections_with_results: number;
      pct: number;
    };
  };
};
type TrainingSessionShowResponse = {
  ok: boolean;
  data: {
    assignment_id: number | null;
    source: 'free';
    status: TrainingStatus | null;
    scheduled_for: string | null;

    training_session: TrainingSessionDTO;
    sections: Array<{
      id: number;
      order: number;
      name: string;
      description: string | null;
      video_url: string | null;
      accepts_results: boolean;
      result_type: string | null;
    }>;

    progress: {
      sections_total: number;
      sections_with_results: number;
      pct: number;
    };
  };
};
export type StartFreeResponse = {
  ok: boolean;
  data: {
    assignment_id: number;
    scheduled_for: string; // YYYY-MM-DD
    status: TrainingStatus;
  };
};
export type TrainingAssignmentStatusResponse = {
  ok: boolean;
  data: {
    status: TrainingStatus;
  };
};

@Injectable({ providedIn: 'root' })
export class TrainingApiService {
  constructor(private api: ApiService) {}

  /**
   * GET /api/v1/app/trainings
   * params opcionales: from, to, status, include=free
   */
  index(params?: { from?: string; to?: string; status?: string; include?: 'free' }) {
    return this.api.get<TrainingsIndexResponse>('app/trainings', params);
  }

  /**
   * GET /api/v1/app/training-assignments/{assignment}
   * Mapea el payload real del backend a TrainingDetailDTO (UI).
   */
  async show(assignmentId: number): Promise<{ ok: boolean; data: TrainingDetailDTO }> {
    const resp = await this.api.get<TrainingAssignmentShowResponse>(
      `app/training-assignments/${assignmentId}`
    );

    if (!resp?.ok) {
      return { ok: false, data: null as any };
    }

    const a = resp.data.assignment;
    const s = resp.data.training_session;

    const safeSession: TrainingSessionDTO =
      s ?? ({
        id: 0,
        coach_id: 0,
        title: 'Entrenamiento',
        duration_minutes: null,
        level: 'beginner',
        goal: null,
        type: null,
        visibility: null,
        notes: null,
        cover_image: null,
      } as TrainingSessionDTO);

    // const sections: TrainingSectionDTO[] = (resp.data.sections ?? []).map((sec) => {
    //   const lr = sec.latest_result;

    //   return {
    //     id: sec.id,
    //     order: sec.order,
    //     name: sec.name,
    //     description: sec.description,
    //     video_url: sec.video_url ?? null,  
    //     accepts_results: !!sec.accepts_results,

    //     // Backend: unit_default
    //     result_type: sec.unit_default ?? null,

    //     completed: !!lr,
    //     result: lr
    //       ? {
    //           value:
    //             typeof lr.value === 'string'
    //               ? lr.value
    //               : lr.value === null || lr.value === undefined
    //                 ? ''
    //                 : typeof lr.value === 'number' || typeof lr.value === 'boolean'
    //                   ? String(lr.value)
    //                   : JSON.stringify(lr.value),
    //           unit: lr.unit ?? null,
    //           notes: lr.notes ?? null,
    //           completed_at: lr.created_at,
    //         }
    //       : null,
    //   };
    // });
    const sections: TrainingSectionDTO[] = (resp.data.sections ?? []).map((sec: any) => {
  // Nuevo backend: sec.result (o compat: sec.latest_result)
  const r = sec.result ?? sec.latest_result ?? null;

  return {
    id: sec.id,
    order: sec.order,
    name: sec.name,
    description: sec.description,
    video_url: sec.video_url ?? null,
    accepts_results: !!sec.accepts_results,

    // ✅ Nuevo: result_type real (number|time|text|bool|json|null)
    result_type: sec.result_type ?? null,

    // ✅ Nuevo: completado viene del backend (results o completions)
    completed: !!(sec.is_completed ?? sec.completed ?? r),

    // ✅ Resultado normalizado para tu HTML (mantiene completed_at)
    result: r
      ? {
          value: r.value,
          unit: r.unit ?? null,
          notes: r.notes ?? null,
          completed_at:
            r.recorded_at ??
            r.updated_at ??
            r.created_at ??
            new Date().toISOString(),
        }
      : null,
  };
});


    const detail: TrainingDetailDTO = {
      assignment_id: a.id,
      source: 'personal',
      status: a.status,
      scheduled_for: a.scheduled_for ?? '',

      started_at: null,
      completed_at: null,

      training_session: {
        id: safeSession.id,
        coach_id: safeSession.coach_id,
        title: safeSession.title,
        duration_minutes: safeSession.duration_minutes ?? null,
        level: safeSession.level,
        
        goal: safeSession.goal ?? null,
        type: safeSession.type ?? null,
        visibility: safeSession.visibility ?? null,
        notes: safeSession.notes ?? null,
        cover_image: safeSession.cover_image ?? null,
      },

      group: null,

      progress: {
        sections_total: resp.data.progress.sections_total,
        sections_completed: resp.data.progress.sections_with_results,
        pct: resp.data.progress.pct,
      },

      sections,
    };

    return { ok: true, data: detail };
  }
async showFree(sessionId: number): Promise<{ ok: boolean; data: TrainingDetailDTO }> {
  const resp = await this.api.get<TrainingSessionShowResponse>(
    `app/training-sessions/${sessionId}`
  );

  if (!resp?.ok) {
    return { ok: false, data: null as any };
  }

  const d = resp.data;
type ResultType = 'number' | 'time' | 'text' | 'bool' | 'json';
const allowedTypes: ResultType[] = ['number', 'time', 'text', 'bool', 'json'];
  const sections: TrainingSectionDTO[] = (d.sections ?? []).map((sec: any) => {
  const rtRaw = sec.result_type ?? null;
  const rt: ResultType | null =
    rtRaw && allowedTypes.includes(rtRaw) ? rtRaw : null;

  return {
    id: sec.id,
    order: sec.order,
    name: sec.name,
    description: sec.description ?? '',
    video_url: sec.video_url ?? null,
    accepts_results: !!sec.accepts_results,
    result_type: rt,

    // free no tiene results todavía
    completed: false,
    result: null,
  };
});

  const detail: TrainingDetailDTO = {
    assignment_id: null,
    source: 'free',
    status: (d.status ?? 'scheduled') as any, // UI requiere TrainingStatus; puedes dejar 'scheduled'
    scheduled_for: d.scheduled_for ?? '',

    started_at: null,
    completed_at: null,

    training_session: {
      id: d.training_session.id,
      coach_id: d.training_session.coach_id,
      title: d.training_session.title,
      duration_minutes: d.training_session.duration_minutes ?? null,
      level: d.training_session.level,
      goal: d.training_session.goal ?? null,
      type: d.training_session.type ?? null,
      visibility: d.training_session.visibility ?? null,
      notes: d.training_session.notes ?? null,
      cover_image: d.training_session.cover_image ?? null,
    },

    group: null,

    progress: {
      sections_total: d.progress.sections_total,
      sections_completed: d.progress.sections_with_results,
      pct: d.progress.pct,
    },

    sections,
  };

  return { ok: true, data: detail };
}
async saveSectionResult(
  assignmentId: number,
  sectionId: number,
  payload: SaveSectionResultPayload
): Promise<any> {
  // ✅ AJUSTA ESTA RUTA A TU BACKEND REAL
  // Ejemplos comunes:
  // POST /api/v1/app/client/training-assignments/{assignment}/sections/{section}/results
  // POST /api/v1/app/client/training-assignments/{assignment}/sections/{section}/result
  // const url = `app/training-assignments/${assignmentId}/sections/${sectionId}/results`;
  const url = `app/training-sections/${sectionId}/results`;
  return this.api.post(url, payload); // o this.apiCtrl.post(...) según tu wrapper
}
  /**
   * POST /api/v1/app/training-assignments/{assignment}/start
   */
  start(assignmentId: number): Promise<TrainingAssignmentStatusResponse> {
    return this.api.post<TrainingAssignmentStatusResponse>(
      `app/training-assignments/${assignmentId}/start`,
      {}
    );
  }

  /**
   * POST /api/v1/app/training-assignments/{assignment}/complete
   */
  complete(assignmentId: number): Promise<TrainingAssignmentStatusResponse> {
    return this.api.post<TrainingAssignmentStatusResponse>(
      `app/training-assignments/${assignmentId}/complete`,
      {}
    );
  }
  completeSection(assignmentId: number, sectionId: number): Promise<{ ok: boolean; message?: string }> {
  return this.api.post<{ ok: boolean; message?: string }>(
    `app/training-assignments/${assignmentId}/sections/${sectionId}/complete`,
    {}
  );
}
startFreeSession(sessionId: number): Promise<StartFreeResponse> {
  return this.api.post<StartFreeResponse>(`app/training-sessions/${sessionId}/start`, {});
}
}
