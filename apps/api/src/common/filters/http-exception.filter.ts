import {
  ArgumentsHost,
  Catch,
  ExceptionFilter,
  HttpException,
  HttpStatus,
  Logger,
} from '@nestjs/common';
import type { Response } from 'express';

const DEFAULT_CODES: Record<number, string> = {
  [HttpStatus.BAD_REQUEST]: 'INVALID_INPUT',
  [HttpStatus.UNAUTHORIZED]: 'UNAUTHORIZED',
  [HttpStatus.FORBIDDEN]: 'FORBIDDEN',
  [HttpStatus.NOT_FOUND]: 'NOT_FOUND',
  [HttpStatus.CONFLICT]: 'CONFLICT',
  [HttpStatus.INTERNAL_SERVER_ERROR]: 'INTERNAL_ERROR',
};

interface StructuredErrorBody {
  code?: string;
  message?: string | string[];
}

// Uniformise toutes les erreurs au format { success: false, error: { code, message } }
// defini dans CONVENTIONS.md section 6.
@Catch()
export class HttpExceptionFilter implements ExceptionFilter {
  private readonly logger = new Logger(HttpExceptionFilter.name);

  catch(exception: unknown, host: ArgumentsHost) {
    const ctx = host.switchToHttp();
    const response = ctx.getResponse<Response>();

    const status =
      exception instanceof HttpException
        ? exception.getStatus()
        : HttpStatus.INTERNAL_SERVER_ERROR;

    let code = DEFAULT_CODES[status] ?? 'INTERNAL_ERROR';
    let message = 'Une erreur est survenue.';

    if (exception instanceof HttpException) {
      const body = exception.getResponse();
      if (typeof body === 'string') {
        message = body;
      } else if (typeof body === 'object' && body !== null) {
        const structured = body as StructuredErrorBody;
        if (structured.code) {
          code = structured.code;
        }
        if (Array.isArray(structured.message)) {
          message = structured.message.join(' ');
        } else if (structured.message) {
          message = structured.message;
        }
      }
    } else {
      this.logger.error(exception);
    }

    response.status(status).json({
      success: false,
      error: { code, message },
    });
  }
}
