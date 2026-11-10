-- Reference structure only. Install with Laravel migrations, not by importing this snapshot.
--
-- PostgreSQL database dump
--


-- Dumped from database version 17.11
-- Dumped by pg_dump version 17.11

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: nfcheck_online; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA "nfcheck_online";


SET default_tablespace = '';

SET default_table_access_method = "heap";

--
-- Name: access_authorizations; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."access_authorizations" (
    "id" bigint NOT NULL,
    "person_id" bigint NOT NULL,
    "status" character varying(255) DEFAULT 'active'::character varying NOT NULL,
    "valid_from" timestamp(6) without time zone NOT NULL,
    "valid_until" timestamp(6) without time zone,
    "reason" character varying(255),
    "granted_by" bigint NOT NULL,
    "revoked_by" bigint,
    "revoked_at" timestamp(6) without time zone,
    "created_at" timestamp(6) without time zone,
    "updated_at" timestamp(6) without time zone,
    CONSTRAINT "access_authorizations_status_check" CHECK ((("status")::"text" = ANY ((ARRAY['active'::character varying, 'revoked'::character varying])::"text"[]))),
    CONSTRAINT "authorization_valid_interval" CHECK ((("valid_until" IS NULL) OR ("valid_until" > "valid_from"))),
    CONSTRAINT "nfcheck_nonnegative_13ad15bd168b" CHECK (("revoked_by" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_278dd609b120" CHECK (("id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_855ebf05809f" CHECK (("granted_by" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_e17d30d3e74e" CHECK (("person_id" >= 0))
);


--
-- Name: access_authorizations_id_seq; Type: SEQUENCE; Schema: nfcheck_online; Owner: -
--

CREATE SEQUENCE "nfcheck_online"."access_authorizations_id_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: access_authorizations_id_seq; Type: SEQUENCE OWNED BY; Schema: nfcheck_online; Owner: -
--

ALTER SEQUENCE "nfcheck_online"."access_authorizations_id_seq" OWNED BY "nfcheck_online"."access_authorizations"."id";


--
-- Name: access_events; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."access_events" (
    "id" bigint NOT NULL,
    "public_id" "uuid" NOT NULL,
    "scan_attempt_id" bigint NOT NULL,
    "person_id" bigint NOT NULL,
    "credential_assignment_id" bigint NOT NULL,
    "guard_id" bigint NOT NULL,
    "gate_id" bigint NOT NULL,
    "direction" character varying(255) NOT NULL,
    "source" character varying(255) NOT NULL,
    "policy_version" character varying(32) NOT NULL,
    "identity_method" character varying(255) NOT NULL,
    "sequence_notice" character varying(40),
    "confirmed_at" timestamp(6) without time zone NOT NULL,
    CONSTRAINT "access_events_direction_check" CHECK ((("direction")::"text" = ANY ((ARRAY['entry'::character varying, 'exit'::character varying])::"text"[]))),
    CONSTRAINT "access_events_identity_method_check" CHECK ((("identity_method")::"text" = ANY ((ARRAY['photo'::character varying, 'campus_document'::character varying, 'nfc_scan'::character varying])::"text"[]))),
    CONSTRAINT "access_events_source_check" CHECK ((("source")::"text" = ANY ((ARRAY['physical'::character varying, 'simulated'::character varying])::"text"[]))),
    CONSTRAINT "nfcheck_nonnegative_01d819ff67e6" CHECK (("scan_attempt_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_2653d2742692" CHECK (("person_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_434f6415de3c" CHECK (("id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_70e25284ada2" CHECK (("credential_assignment_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_cc0c751cebc9" CHECK (("guard_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_e080e056ab00" CHECK (("gate_id" >= 0))
);


--
-- Name: access_events_id_seq; Type: SEQUENCE; Schema: nfcheck_online; Owner: -
--

CREATE SEQUENCE "nfcheck_online"."access_events_id_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: access_events_id_seq; Type: SEQUENCE OWNED BY; Schema: nfcheck_online; Owner: -
--

ALTER SEQUENCE "nfcheck_online"."access_events_id_seq" OWNED BY "nfcheck_online"."access_events"."id";


--
-- Name: anomaly_evidence; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."anomaly_evidence" (
    "anomaly_flag_id" bigint NOT NULL,
    "scan_attempt_id" bigint NOT NULL,
    CONSTRAINT "nfcheck_nonnegative_56cc84103d1b" CHECK (("scan_attempt_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_5c28c8984c2e" CHECK (("anomaly_flag_id" >= 0))
);


--
-- Name: anomaly_flags; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."anomaly_flags" (
    "id" bigint NOT NULL,
    "public_id" "uuid" NOT NULL,
    "source" character varying(255) NOT NULL,
    "rule_code" character varying(48) NOT NULL,
    "rule_version" character(64) NOT NULL,
    "policy" json NOT NULL,
    "severity" character varying(255) NOT NULL,
    "anchor_attempt_id" bigint NOT NULL,
    "explanation" "text" NOT NULL,
    "occurred_at" timestamp(6) without time zone NOT NULL,
    "created_at" timestamp(6) without time zone NOT NULL,
    CONSTRAINT "anomaly_flags_severity_check" CHECK ((("severity")::"text" = ANY ((ARRAY['low'::character varying, 'medium'::character varying])::"text"[]))),
    CONSTRAINT "anomaly_flags_source_check" CHECK ((("source")::"text" = ANY ((ARRAY['physical'::character varying, 'simulated'::character varying])::"text"[]))),
    CONSTRAINT "nfcheck_nonnegative_11f61475df2a" CHECK (("anchor_attempt_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_774f777c9b9b" CHECK (("id" >= 0))
);


--
-- Name: anomaly_flags_id_seq; Type: SEQUENCE; Schema: nfcheck_online; Owner: -
--

CREATE SEQUENCE "nfcheck_online"."anomaly_flags_id_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: anomaly_flags_id_seq; Type: SEQUENCE OWNED BY; Schema: nfcheck_online; Owner: -
--

ALTER SEQUENCE "nfcheck_online"."anomaly_flags_id_seq" OWNED BY "nfcheck_online"."anomaly_flags"."id";


--
-- Name: anomaly_reviews; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."anomaly_reviews" (
    "id" bigint NOT NULL,
    "anomaly_flag_id" bigint NOT NULL,
    "actor_id" bigint NOT NULL,
    "status" character varying(255) NOT NULL,
    "reason" "text" NOT NULL,
    "created_at" timestamp(6) without time zone NOT NULL,
    CONSTRAINT "anomaly_reviews_status_check" CHECK ((("status")::"text" = ANY ((ARRAY['open'::character varying, 'in_review'::character varying, 'resolved'::character varying, 'false_alarm'::character varying])::"text"[]))),
    CONSTRAINT "nfcheck_nonnegative_6b6a920cea72" CHECK (("anomaly_flag_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_7041b7749852" CHECK (("id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_dd0f3b103c24" CHECK (("actor_id" >= 0))
);


--
-- Name: anomaly_reviews_id_seq; Type: SEQUENCE; Schema: nfcheck_online; Owner: -
--

CREATE SEQUENCE "nfcheck_online"."anomaly_reviews_id_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: anomaly_reviews_id_seq; Type: SEQUENCE OWNED BY; Schema: nfcheck_online; Owner: -
--

ALTER SEQUENCE "nfcheck_online"."anomaly_reviews_id_seq" OWNED BY "nfcheck_online"."anomaly_reviews"."id";


--
-- Name: audit_logs; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."audit_logs" (
    "id" bigint NOT NULL,
    "actor_id" bigint,
    "action" character varying(80) NOT NULL,
    "target_type" character varying(80),
    "target_id" bigint,
    "outcome" character varying(255) NOT NULL,
    "metadata" json,
    "created_at" timestamp(6) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT "audit_logs_outcome_check" CHECK ((("outcome")::"text" = ANY ((ARRAY['success'::character varying, 'failure'::character varying, 'denied'::character varying])::"text"[]))),
    CONSTRAINT "nfcheck_nonnegative_176308cfbda6" CHECK (("actor_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_505415f53557" CHECK (("target_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_b92342b99d0c" CHECK (("id" >= 0))
);


--
-- Name: audit_logs_id_seq; Type: SEQUENCE; Schema: nfcheck_online; Owner: -
--

CREATE SEQUENCE "nfcheck_online"."audit_logs_id_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: audit_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: nfcheck_online; Owner: -
--

ALTER SEQUENCE "nfcheck_online"."audit_logs_id_seq" OWNED BY "nfcheck_online"."audit_logs"."id";


--
-- Name: cache; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."cache" (
    "key" character varying(255) NOT NULL,
    "value" "text" NOT NULL,
    "expiration" integer NOT NULL,
    CONSTRAINT "nfcheck_nonnegative_58f33793178c" CHECK (("expiration" >= 0))
);


--
-- Name: cache_locks; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."cache_locks" (
    "key" character varying(255) NOT NULL,
    "owner" character varying(255) NOT NULL,
    "expiration" integer NOT NULL,
    CONSTRAINT "nfcheck_nonnegative_d15d686964fe" CHECK (("expiration" >= 0))
);


--
-- Name: campus_departments; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."campus_departments" (
    "name" character varying(120) NOT NULL
);


--
-- Name: campus_programs; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."campus_programs" (
    "code" character varying(32) NOT NULL,
    "name" character varying(180) NOT NULL,
    "max_year_level" smallint DEFAULT '4'::smallint NOT NULL,
    CONSTRAINT "nfcheck_nonnegative_38a03eeb0223" CHECK (("max_year_level" >= 0))
);


--
-- Name: credential_assignments; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."credential_assignments" (
    "id" bigint NOT NULL,
    "person_id" bigint NOT NULL,
    "credential_id" bigint NOT NULL,
    "assigned_by" bigint NOT NULL,
    "assigned_at" timestamp(6) without time zone NOT NULL,
    "ended_at" timestamp(6) without time zone,
    "ended_by" bigint,
    "end_reason" character varying(40),
    CONSTRAINT "credential_assignment_end_order" CHECK ((("ended_at" IS NULL) OR ("ended_at" >= "assigned_at"))),
    CONSTRAINT "nfcheck_nonnegative_21db093f8f65" CHECK (("id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_d888b4f1d4d6" CHECK (("ended_by" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_df68eac9c2b5" CHECK (("credential_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_ecbba7c8e5e9" CHECK (("person_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_ffc3e4e73d10" CHECK (("assigned_by" >= 0))
);


--
-- Name: credential_assignments_id_seq; Type: SEQUENCE; Schema: nfcheck_online; Owner: -
--

CREATE SEQUENCE "nfcheck_online"."credential_assignments_id_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: credential_assignments_id_seq; Type: SEQUENCE OWNED BY; Schema: nfcheck_online; Owner: -
--

ALTER SEQUENCE "nfcheck_online"."credential_assignments_id_seq" OWNED BY "nfcheck_online"."credential_assignments"."id";


--
-- Name: credential_counters; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."credential_counters" (
    "kind" character varying(16) NOT NULL,
    "last_number" bigint DEFAULT '0'::bigint NOT NULL,
    CONSTRAINT "credential_counter_kind" CHECK ((("kind")::"text" = ANY ((ARRAY['student'::character varying, 'employee'::character varying])::"text"[]))),
    CONSTRAINT "credential_counter_nonnegative" CHECK (("last_number" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_7c61a61d5b76" CHECK (("last_number" >= 0))
);


--
-- Name: employee_profiles; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."employee_profiles" (
    "person_id" bigint NOT NULL,
    "department" character varying(120) NOT NULL,
    "position" character varying(120) NOT NULL,
    "created_at" timestamp(6) without time zone,
    "updated_at" timestamp(6) without time zone,
    CONSTRAINT "nfcheck_nonnegative_b11006cb7686" CHECK (("person_id" >= 0))
);


--
-- Name: gates; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."gates" (
    "id" bigint NOT NULL,
    "code" character varying(32) NOT NULL,
    "name" character varying(120) NOT NULL,
    "status" character varying(255) DEFAULT 'active'::character varying NOT NULL,
    "created_at" timestamp(6) without time zone,
    "updated_at" timestamp(6) without time zone,
    "archived_at" timestamp(6) without time zone,
    "record_version" bigint DEFAULT '1'::bigint NOT NULL,
    CONSTRAINT "gates_status_check" CHECK ((("status")::"text" = ANY ((ARRAY['active'::character varying, 'inactive'::character varying])::"text"[]))),
    CONSTRAINT "nfcheck_nonnegative_a98d33aca59e" CHECK (("record_version" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_ff2335abe4f5" CHECK (("id" >= 0))
);


--
-- Name: gates_id_seq; Type: SEQUENCE; Schema: nfcheck_online; Owner: -
--

CREATE SEQUENCE "nfcheck_online"."gates_id_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: gates_id_seq; Type: SEQUENCE OWNED BY; Schema: nfcheck_online; Owner: -
--

ALTER SEQUENCE "nfcheck_online"."gates_id_seq" OWNED BY "nfcheck_online"."gates"."id";


--
-- Name: guard_gate_assignments; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."guard_gate_assignments" (
    "id" bigint NOT NULL,
    "guard_id" bigint NOT NULL,
    "gate_id" bigint NOT NULL,
    "starts_at" timestamp(6) without time zone NOT NULL,
    "ends_at" timestamp(6) without time zone,
    "assigned_by" bigint NOT NULL,
    "revoked_by" bigint,
    "revoked_at" timestamp(6) without time zone,
    "created_at" timestamp(6) without time zone,
    "updated_at" timestamp(6) without time zone,
    CONSTRAINT "assignment_valid_interval" CHECK ((("ends_at" IS NULL) OR ("ends_at" > "starts_at"))),
    CONSTRAINT "nfcheck_nonnegative_3857a082f11e" CHECK (("revoked_by" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_c7ec55371f8f" CHECK (("assigned_by" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_c964c2a76634" CHECK (("id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_dea6549bd706" CHECK (("guard_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_eb01d0b0de65" CHECK (("gate_id" >= 0))
);


--
-- Name: guard_gate_assignments_id_seq; Type: SEQUENCE; Schema: nfcheck_online; Owner: -
--

CREATE SEQUENCE "nfcheck_online"."guard_gate_assignments_id_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: guard_gate_assignments_id_seq; Type: SEQUENCE OWNED BY; Schema: nfcheck_online; Owner: -
--

ALTER SEQUENCE "nfcheck_online"."guard_gate_assignments_id_seq" OWNED BY "nfcheck_online"."guard_gate_assignments"."id";


--
-- Name: migrations; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."migrations" (
    "id" integer NOT NULL,
    "migration" character varying(255) NOT NULL,
    "batch" integer NOT NULL,
    CONSTRAINT "nfcheck_nonnegative_9d4038f63144" CHECK (("id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_e79a8a641998" CHECK (("batch" >= 0))
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: nfcheck_online; Owner: -
--

CREATE SEQUENCE "nfcheck_online"."migrations_id_seq"
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: nfcheck_online; Owner: -
--

ALTER SEQUENCE "nfcheck_online"."migrations_id_seq" OWNED BY "nfcheck_online"."migrations"."id";


--
-- Name: nfc_credentials; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."nfc_credentials" (
    "id" bigint NOT NULL,
    "public_id" "uuid" NOT NULL,
    "label" character varying(64) NOT NULL,
    "token_digest" "bytea" NOT NULL,
    "status" character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    "source" character varying(255) NOT NULL,
    "created_by" bigint NOT NULL,
    "issuer_auth_version" bigint NOT NULL,
    "issuance_request_id" "uuid" NOT NULL,
    "completion_digest" "bytea" NOT NULL,
    "issuer_session_digest" "bytea" NOT NULL,
    "person_version" bigint NOT NULL,
    "issuance_mode" character varying(255) NOT NULL,
    "replaces_credential_id" bigint,
    "pending_expires_at" timestamp(6) without time zone NOT NULL,
    "read_back_at" timestamp(6) without time zone,
    "activated_at" timestamp(6) without time zone,
    "valid_until" timestamp(6) without time zone,
    "closed_at" timestamp(6) without time zone,
    "closed_by" bigint,
    "close_reason" character varying(40),
    "created_at" timestamp(6) without time zone,
    "updated_at" timestamp(6) without time zone,
    CONSTRAINT "credential_expiry_after_creation" CHECK ((("valid_until" IS NULL) OR ("valid_until" > "created_at"))),
    CONSTRAINT "nfc_credentials_issuance_mode_check" CHECK ((("issuance_mode")::"text" = ANY ((ARRAY['new'::character varying, 'replace'::character varying, 'lost'::character varying])::"text"[]))),
    CONSTRAINT "nfc_credentials_source_check" CHECK ((("source")::"text" = ANY ((ARRAY['physical'::character varying, 'simulated'::character varying])::"text"[]))),
    CONSTRAINT "nfc_credentials_status_check" CHECK ((("status")::"text" = ANY ((ARRAY['pending'::character varying, 'active'::character varying, 'revoked'::character varying, 'cancelled'::character varying, 'expired'::character varying])::"text"[]))),
    CONSTRAINT "nfcheck_digest_02e1c8f85a0f" CHECK (("octet_length"("issuer_session_digest") = 32)),
    CONSTRAINT "nfcheck_digest_0ee93c1d212e" CHECK (("octet_length"("completion_digest") = 32)),
    CONSTRAINT "nfcheck_digest_763e66f30149" CHECK (("octet_length"("token_digest") = 32)),
    CONSTRAINT "nfcheck_nonnegative_05814cec1139" CHECK (("person_version" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_19ac024edc06" CHECK (("closed_by" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_348f7bb625ac" CHECK (("issuer_auth_version" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_80653738cc45" CHECK (("replaces_credential_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_c8c49ab8a750" CHECK (("id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_e0a3db451651" CHECK (("created_by" >= 0))
);


--
-- Name: nfc_credentials_id_seq; Type: SEQUENCE; Schema: nfcheck_online; Owner: -
--

CREATE SEQUENCE "nfcheck_online"."nfc_credentials_id_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: nfc_credentials_id_seq; Type: SEQUENCE OWNED BY; Schema: nfcheck_online; Owner: -
--

ALTER SEQUENCE "nfcheck_online"."nfc_credentials_id_seq" OWNED BY "nfcheck_online"."nfc_credentials"."id";


--
-- Name: passage_corrections; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."passage_corrections" (
    "id" bigint NOT NULL,
    "public_id" "uuid" NOT NULL,
    "access_event_id" bigint NOT NULL,
    "actor_id" bigint NOT NULL,
    "action" character varying(255) NOT NULL,
    "reason" "text" NOT NULL,
    "created_at" timestamp(6) without time zone NOT NULL,
    CONSTRAINT "nfcheck_nonnegative_4e5bc5694512" CHECK (("id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_92150817f75c" CHECK (("actor_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_c87ecaf6792b" CHECK (("access_event_id" >= 0)),
    CONSTRAINT "passage_corrections_action_check" CHECK ((("action")::"text" = ANY ((ARRAY['note'::character varying, 'void'::character varying, 'reinstate'::character varying])::"text"[])))
);


--
-- Name: passage_corrections_id_seq; Type: SEQUENCE; Schema: nfcheck_online; Owner: -
--

CREATE SEQUENCE "nfcheck_online"."passage_corrections_id_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: passage_corrections_id_seq; Type: SEQUENCE OWNED BY; Schema: nfcheck_online; Owner: -
--

ALTER SEQUENCE "nfcheck_online"."passage_corrections_id_seq" OWNED BY "nfcheck_online"."passage_corrections"."id";


--
-- Name: password_reset_tokens; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."password_reset_tokens" (
    "email" character varying(255) NOT NULL,
    "token" character varying(255) NOT NULL,
    "created_at" timestamp(0) without time zone
);


--
-- Name: persons; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."persons" (
    "id" bigint NOT NULL,
    "person_type" character varying(255) NOT NULL,
    "campus_number" character varying(64) NOT NULL,
    "full_name" character varying(120) NOT NULL,
    "photo_path" character varying(255),
    "status" character varying(255) DEFAULT 'active'::character varying NOT NULL,
    "created_at" timestamp(6) without time zone,
    "updated_at" timestamp(6) without time zone,
    "archived_at" timestamp(6) without time zone,
    "record_version" bigint DEFAULT '1'::bigint NOT NULL,
    "active_credential_assignment_id" bigint,
    CONSTRAINT "nfcheck_nonnegative_5b61670e6476" CHECK (("active_credential_assignment_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_a1c2eccc9349" CHECK (("record_version" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_d38a98bc2f53" CHECK (("id" >= 0)),
    CONSTRAINT "persons_person_type_check" CHECK ((("person_type")::"text" = ANY ((ARRAY['student'::character varying, 'employee'::character varying])::"text"[]))),
    CONSTRAINT "persons_status_check" CHECK ((("status")::"text" = ANY ((ARRAY['active'::character varying, 'inactive'::character varying])::"text"[])))
);


--
-- Name: persons_id_seq; Type: SEQUENCE; Schema: nfcheck_online; Owner: -
--

CREATE SEQUENCE "nfcheck_online"."persons_id_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: persons_id_seq; Type: SEQUENCE OWNED BY; Schema: nfcheck_online; Owner: -
--

ALTER SEQUENCE "nfcheck_online"."persons_id_seq" OWNED BY "nfcheck_online"."persons"."id";


--
-- Name: scan_attempts; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."scan_attempts" (
    "id" bigint NOT NULL,
    "public_id" "uuid" NOT NULL,
    "request_id" "uuid" NOT NULL,
    "guard_id" bigint NOT NULL,
    "guard_auth_version" bigint NOT NULL,
    "session_digest" "bytea" NOT NULL,
    "gate_id" bigint NOT NULL,
    "gate_assignment_id" bigint,
    "payload_fingerprint" "bytea" NOT NULL,
    "direction" character varying(255) NOT NULL,
    "source" character varying(255) NOT NULL,
    "person_id" bigint,
    "person_version" bigint,
    "credential_id" bigint,
    "credential_assignment_id" bigint,
    "authorization_id" bigint,
    "outcome" character varying(255) NOT NULL,
    "reason" character varying(64) NOT NULL,
    "policy_version" character varying(32) NOT NULL,
    "duplicate_window_seconds" smallint NOT NULL,
    "received_at" timestamp(6) without time zone NOT NULL,
    "review_expires_at" timestamp(6) without time zone,
    "review_status" character varying(255) NOT NULL,
    "reviewed_at" timestamp(6) without time zone,
    "reviewed_by" bigint,
    "review_reason" character varying(64),
    "duplicate_of_event_id" bigint,
    "created_at" timestamp(6) without time zone,
    "updated_at" timestamp(6) without time zone,
    CONSTRAINT "attempt_duplicate_reason_present" CHECK ((("duplicate_of_event_id" IS NULL) OR ("review_reason" IS NOT NULL))),
    CONSTRAINT "attempt_duplicate_review" CHECK ((("duplicate_of_event_id" IS NULL) OR ((("review_status")::"text" = 'cancelled'::"text") AND (("review_reason")::"text" = 'duplicate_passage'::"text")))),
    CONSTRAINT "attempt_outcome_review" CHECK ((((("outcome")::"text" = 'denied'::"text") AND (("review_status")::"text" = 'not_applicable'::"text") AND ("review_expires_at" IS NULL)) OR ((("outcome")::"text" = 'allowed'::"text") AND (("review_status")::"text" <> 'not_applicable'::"text") AND ("person_id" IS NOT NULL) AND ("credential_id" IS NOT NULL) AND ("credential_assignment_id" IS NOT NULL) AND ("person_version" IS NOT NULL) AND ((((("policy_version")::"text" = 'gate-auto-v2'::"text") OR (("policy_version")::"text" ~~ 'gate-state-v1-%'::"text")) AND (("review_status")::"text" = ANY ((ARRAY['confirmed'::character varying, 'cancelled'::character varying])::"text"[])) AND ("review_expires_at" IS NULL)) OR ((NOT ((("policy_version")::"text" = 'gate-auto-v2'::"text") OR (("policy_version")::"text" ~~ 'gate-state-v1-%'::"text"))) AND ("authorization_id" IS NOT NULL) AND ("review_expires_at" IS NOT NULL) AND ("review_expires_at" > "received_at")))))),
    CONSTRAINT "attempt_review_clock" CHECK ((((("review_status")::"text" = ANY ((ARRAY['pending'::character varying, 'not_applicable'::character varying])::"text"[])) AND ("reviewed_at" IS NULL)) OR ((("review_status")::"text" = ANY ((ARRAY['confirmed'::character varying, 'identity_mismatch'::character varying, 'cancelled'::character varying, 'expired'::character varying])::"text"[])) AND ("reviewed_at" IS NOT NULL)))),
    CONSTRAINT "attempt_reviewer_owner" CHECK (((("reviewed_by" IS NULL) OR ("reviewed_by" = "guard_id")) AND ((("review_status")::"text" <> ALL ((ARRAY['confirmed'::character varying, 'identity_mismatch'::character varying, 'cancelled'::character varying])::"text"[])) OR ("reviewed_by" IS NOT NULL)))),
    CONSTRAINT "nfcheck_digest_3636c1e82a75" CHECK (("octet_length"("payload_fingerprint") = 32)),
    CONSTRAINT "nfcheck_digest_85c1d859bc5a" CHECK (("octet_length"("session_digest") = 32)),
    CONSTRAINT "nfcheck_nonnegative_176a4582bf1d" CHECK (("guard_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_299df2e5f02b" CHECK (("guard_auth_version" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_3621e927cfa9" CHECK (("person_version" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_3b294ecf914b" CHECK (("id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_478089d6cef5" CHECK (("gate_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_482345d28ade" CHECK (("duplicate_window_seconds" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_6dc072fe2ce9" CHECK (("credential_assignment_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_a47535f9d9b8" CHECK (("credential_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_c899afecd65a" CHECK (("reviewed_by" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_d75c8348516d" CHECK (("gate_assignment_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_d915133f4652" CHECK (("duplicate_of_event_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_da757b11c96f" CHECK (("person_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_f6a51162701b" CHECK (("authorization_id" >= 0)),
    CONSTRAINT "scan_attempts_direction_check" CHECK ((("direction")::"text" = ANY ((ARRAY['entry'::character varying, 'exit'::character varying])::"text"[]))),
    CONSTRAINT "scan_attempts_outcome_check" CHECK ((("outcome")::"text" = ANY ((ARRAY['allowed'::character varying, 'denied'::character varying])::"text"[]))),
    CONSTRAINT "scan_attempts_review_status_check" CHECK ((("review_status")::"text" = ANY ((ARRAY['pending'::character varying, 'not_applicable'::character varying, 'confirmed'::character varying, 'identity_mismatch'::character varying, 'cancelled'::character varying, 'expired'::character varying])::"text"[]))),
    CONSTRAINT "scan_attempts_source_check" CHECK ((("source")::"text" = ANY ((ARRAY['physical'::character varying, 'simulated'::character varying])::"text"[])))
);


--
-- Name: scan_attempts_id_seq; Type: SEQUENCE; Schema: nfcheck_online; Owner: -
--

CREATE SEQUENCE "nfcheck_online"."scan_attempts_id_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: scan_attempts_id_seq; Type: SEQUENCE OWNED BY; Schema: nfcheck_online; Owner: -
--

ALTER SEQUENCE "nfcheck_online"."scan_attempts_id_seq" OWNED BY "nfcheck_online"."scan_attempts"."id";


--
-- Name: sessions; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."sessions" (
    "id" character varying(255) NOT NULL,
    "user_id" bigint,
    "ip_address" character varying(45),
    "user_agent" "text",
    "payload" "text" NOT NULL,
    "last_activity" integer NOT NULL,
    CONSTRAINT "nfcheck_nonnegative_a85c368ebb2f" CHECK (("user_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_e5bf49d5fc21" CHECK (("last_activity" >= 0))
);


--
-- Name: student_profiles; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."student_profiles" (
    "person_id" bigint NOT NULL,
    "program" character varying(120) NOT NULL,
    "year_level" smallint,
    "academic_term" character varying(50),
    "created_at" timestamp(6) without time zone,
    "updated_at" timestamp(6) without time zone,
    "academic_year" character varying(9),
    "semester" character varying(20),
    CONSTRAINT "nfcheck_nonnegative_65f0e8581b2f" CHECK (("year_level" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_8af81be05eb4" CHECK (("person_id" >= 0)),
    CONSTRAINT "student_year_positive" CHECK ((("year_level" IS NULL) OR ("year_level" > 0)))
);


--
-- Name: summary_controls; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."summary_controls" (
    "id" smallint NOT NULL,
    "quota_day" "date" NOT NULL,
    "claims" integer NOT NULL,
    CONSTRAINT "nfcheck_nonnegative_23a24d9cf95d" CHECK (("id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_303a2dda549a" CHECK (("claims" >= 0))
);


--
-- Name: summary_requests; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."summary_requests" (
    "id" bigint NOT NULL,
    "public_id" "uuid" NOT NULL,
    "request_key" "uuid" NOT NULL,
    "actor_id" bigint NOT NULL,
    "actor_auth_version" integer NOT NULL,
    "source" character varying(255) NOT NULL,
    "period" character varying(10) NOT NULL,
    "period_from" "date" NOT NULL,
    "period_to" "date" NOT NULL,
    "snapshot_at" timestamp(6) without time zone NOT NULL,
    "snapshot" json NOT NULL,
    "snapshot_hash" character(64) NOT NULL,
    "contract_version" character varying(32) NOT NULL,
    "created_at" timestamp(6) without time zone NOT NULL,
    CONSTRAINT "nfcheck_nonnegative_1fee1d363589" CHECK (("actor_auth_version" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_3e16fda6a006" CHECK (("actor_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_51ddbed27768" CHECK (("id" >= 0)),
    CONSTRAINT "summary_requests_source_check" CHECK ((("source")::"text" = ANY ((ARRAY['physical'::character varying, 'simulated'::character varying])::"text"[])))
);


--
-- Name: summary_requests_id_seq; Type: SEQUENCE; Schema: nfcheck_online; Owner: -
--

CREATE SEQUENCE "nfcheck_online"."summary_requests_id_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: summary_requests_id_seq; Type: SEQUENCE OWNED BY; Schema: nfcheck_online; Owner: -
--

ALTER SEQUENCE "nfcheck_online"."summary_requests_id_seq" OWNED BY "nfcheck_online"."summary_requests"."id";


--
-- Name: summary_results; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."summary_results" (
    "id" bigint NOT NULL,
    "summary_request_id" bigint NOT NULL,
    "status" character varying(255) NOT NULL,
    "selected_facts" json,
    "sentences" json,
    "error_code" character varying(40),
    "provider" character varying(32) NOT NULL,
    "model" character varying(100),
    "input_tokens" integer,
    "output_tokens" integer,
    "created_at" timestamp(6) without time zone NOT NULL,
    CONSTRAINT "nfcheck_nonnegative_0dacf5c27e5f" CHECK (("input_tokens" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_9877e95ab315" CHECK (("output_tokens" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_dde6de252b2f" CHECK (("summary_request_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_fa8a213f6a3e" CHECK (("id" >= 0)),
    CONSTRAINT "summary_results_status_check" CHECK ((("status")::"text" = ANY ((ARRAY['ready'::character varying, 'failed'::character varying, 'unavailable'::character varying, 'rejected'::character varying])::"text"[])))
);


--
-- Name: summary_results_id_seq; Type: SEQUENCE; Schema: nfcheck_online; Owner: -
--

CREATE SEQUENCE "nfcheck_online"."summary_results_id_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: summary_results_id_seq; Type: SEQUENCE OWNED BY; Schema: nfcheck_online; Owner: -
--

ALTER SEQUENCE "nfcheck_online"."summary_results_id_seq" OWNED BY "nfcheck_online"."summary_results"."id";


--
-- Name: summary_reviews; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."summary_reviews" (
    "id" bigint NOT NULL,
    "summary_request_id" bigint NOT NULL,
    "actor_id" bigint NOT NULL,
    "decision" character varying(255) NOT NULL,
    "reason" "text" NOT NULL,
    "created_at" timestamp(6) without time zone NOT NULL,
    CONSTRAINT "nfcheck_nonnegative_33b24a34d75f" CHECK (("summary_request_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_5b77d61d6aa6" CHECK (("id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_e0ab6f55603b" CHECK (("actor_id" >= 0)),
    CONSTRAINT "summary_reviews_decision_check" CHECK ((("decision")::"text" = ANY ((ARRAY['approved'::character varying, 'rejected'::character varying])::"text"[])))
);


--
-- Name: summary_reviews_id_seq; Type: SEQUENCE; Schema: nfcheck_online; Owner: -
--

CREATE SEQUENCE "nfcheck_online"."summary_reviews_id_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: summary_reviews_id_seq; Type: SEQUENCE OWNED BY; Schema: nfcheck_online; Owner: -
--

ALTER SEQUENCE "nfcheck_online"."summary_reviews_id_seq" OWNED BY "nfcheck_online"."summary_reviews"."id";


--
-- Name: summary_tasks; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."summary_tasks" (
    "id" bigint NOT NULL,
    "summary_request_id" bigint NOT NULL,
    "claimed_at" timestamp(6) without time zone,
    "created_at" timestamp(6) without time zone NOT NULL,
    CONSTRAINT "nfcheck_nonnegative_51b6e11ccc3e" CHECK (("id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_ef196acea6d1" CHECK (("summary_request_id" >= 0))
);


--
-- Name: summary_tasks_id_seq; Type: SEQUENCE; Schema: nfcheck_online; Owner: -
--

CREATE SEQUENCE "nfcheck_online"."summary_tasks_id_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: summary_tasks_id_seq; Type: SEQUENCE OWNED BY; Schema: nfcheck_online; Owner: -
--

ALTER SEQUENCE "nfcheck_online"."summary_tasks_id_seq" OWNED BY "nfcheck_online"."summary_tasks"."id";


--
-- Name: users; Type: TABLE; Schema: nfcheck_online; Owner: -
--

CREATE TABLE "nfcheck_online"."users" (
    "id" bigint NOT NULL,
    "person_id" bigint,
    "name" character varying(120) NOT NULL,
    "email" character varying(255) NOT NULL,
    "password" character varying(255) NOT NULL,
    "role" character varying(255) NOT NULL,
    "status" character varying(255) DEFAULT 'active'::character varying NOT NULL,
    "auth_version" bigint DEFAULT '1'::bigint NOT NULL,
    "must_change_password" boolean DEFAULT false NOT NULL,
    "last_login_at" timestamp(6) without time zone,
    "remember_token" character varying(100),
    "created_at" timestamp(6) without time zone,
    "updated_at" timestamp(6) without time zone,
    "archived_at" timestamp(6) without time zone,
    "record_version" bigint DEFAULT '1'::bigint NOT NULL,
    "username" character varying(64),
    "gate_id" bigint,
    CONSTRAINT "nfcheck_nonnegative_0e1c9cbe332a" CHECK (("person_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_4296a49eb4b3" CHECK (("record_version" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_8e1b89f6c2ce" CHECK (("gate_id" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_da898152a9a2" CHECK (("auth_version" >= 0)),
    CONSTRAINT "nfcheck_nonnegative_ea72dfea08f0" CHECK (("id" >= 0)),
    CONSTRAINT "users_role_check" CHECK ((("role")::"text" = ANY ((ARRAY['admin'::character varying, 'guard'::character varying])::"text"[]))),
    CONSTRAINT "users_status_check" CHECK ((("status")::"text" = ANY ((ARRAY['active'::character varying, 'inactive'::character varying])::"text"[])))
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: nfcheck_online; Owner: -
--

CREATE SEQUENCE "nfcheck_online"."users_id_seq"
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: nfcheck_online; Owner: -
--

ALTER SEQUENCE "nfcheck_online"."users_id_seq" OWNED BY "nfcheck_online"."users"."id";


--
-- Name: access_authorizations id; Type: DEFAULT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."access_authorizations" ALTER COLUMN "id" SET DEFAULT "nextval"('"nfcheck_online"."access_authorizations_id_seq"'::"regclass");


--
-- Name: access_events id; Type: DEFAULT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."access_events" ALTER COLUMN "id" SET DEFAULT "nextval"('"nfcheck_online"."access_events_id_seq"'::"regclass");


--
-- Name: anomaly_flags id; Type: DEFAULT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."anomaly_flags" ALTER COLUMN "id" SET DEFAULT "nextval"('"nfcheck_online"."anomaly_flags_id_seq"'::"regclass");


--
-- Name: anomaly_reviews id; Type: DEFAULT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."anomaly_reviews" ALTER COLUMN "id" SET DEFAULT "nextval"('"nfcheck_online"."anomaly_reviews_id_seq"'::"regclass");


--
-- Name: audit_logs id; Type: DEFAULT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."audit_logs" ALTER COLUMN "id" SET DEFAULT "nextval"('"nfcheck_online"."audit_logs_id_seq"'::"regclass");


--
-- Name: credential_assignments id; Type: DEFAULT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."credential_assignments" ALTER COLUMN "id" SET DEFAULT "nextval"('"nfcheck_online"."credential_assignments_id_seq"'::"regclass");


--
-- Name: gates id; Type: DEFAULT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."gates" ALTER COLUMN "id" SET DEFAULT "nextval"('"nfcheck_online"."gates_id_seq"'::"regclass");


--
-- Name: guard_gate_assignments id; Type: DEFAULT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."guard_gate_assignments" ALTER COLUMN "id" SET DEFAULT "nextval"('"nfcheck_online"."guard_gate_assignments_id_seq"'::"regclass");


--
-- Name: migrations id; Type: DEFAULT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."migrations" ALTER COLUMN "id" SET DEFAULT "nextval"('"nfcheck_online"."migrations_id_seq"'::"regclass");


--
-- Name: nfc_credentials id; Type: DEFAULT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."nfc_credentials" ALTER COLUMN "id" SET DEFAULT "nextval"('"nfcheck_online"."nfc_credentials_id_seq"'::"regclass");


--
-- Name: passage_corrections id; Type: DEFAULT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."passage_corrections" ALTER COLUMN "id" SET DEFAULT "nextval"('"nfcheck_online"."passage_corrections_id_seq"'::"regclass");


--
-- Name: persons id; Type: DEFAULT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."persons" ALTER COLUMN "id" SET DEFAULT "nextval"('"nfcheck_online"."persons_id_seq"'::"regclass");


--
-- Name: scan_attempts id; Type: DEFAULT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."scan_attempts" ALTER COLUMN "id" SET DEFAULT "nextval"('"nfcheck_online"."scan_attempts_id_seq"'::"regclass");


--
-- Name: summary_requests id; Type: DEFAULT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."summary_requests" ALTER COLUMN "id" SET DEFAULT "nextval"('"nfcheck_online"."summary_requests_id_seq"'::"regclass");


--
-- Name: summary_results id; Type: DEFAULT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."summary_results" ALTER COLUMN "id" SET DEFAULT "nextval"('"nfcheck_online"."summary_results_id_seq"'::"regclass");


--
-- Name: summary_reviews id; Type: DEFAULT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."summary_reviews" ALTER COLUMN "id" SET DEFAULT "nextval"('"nfcheck_online"."summary_reviews_id_seq"'::"regclass");


--
-- Name: summary_tasks id; Type: DEFAULT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."summary_tasks" ALTER COLUMN "id" SET DEFAULT "nextval"('"nfcheck_online"."summary_tasks_id_seq"'::"regclass");


--
-- Name: users id; Type: DEFAULT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."users" ALTER COLUMN "id" SET DEFAULT "nextval"('"nfcheck_online"."users_id_seq"'::"regclass");


--
-- Name: access_authorizations access_authorizations_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."access_authorizations"
    ADD CONSTRAINT "access_authorizations_pkey" PRIMARY KEY ("id");


--
-- Name: access_events access_events_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."access_events"
    ADD CONSTRAINT "access_events_pkey" PRIMARY KEY ("id");


--
-- Name: access_events access_events_public_id_unique; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."access_events"
    ADD CONSTRAINT "access_events_public_id_unique" UNIQUE ("public_id");


--
-- Name: access_events access_events_scan_attempt_id_unique; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."access_events"
    ADD CONSTRAINT "access_events_scan_attempt_id_unique" UNIQUE ("scan_attempt_id");


--
-- Name: anomaly_flags anomaly_detection_identity; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."anomaly_flags"
    ADD CONSTRAINT "anomaly_detection_identity" UNIQUE ("source", "rule_code", "rule_version", "anchor_attempt_id");


--
-- Name: anomaly_evidence anomaly_evidence_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."anomaly_evidence"
    ADD CONSTRAINT "anomaly_evidence_pkey" PRIMARY KEY ("anomaly_flag_id", "scan_attempt_id");


--
-- Name: anomaly_flags anomaly_flags_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."anomaly_flags"
    ADD CONSTRAINT "anomaly_flags_pkey" PRIMARY KEY ("id");


--
-- Name: anomaly_flags anomaly_flags_public_id_unique; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."anomaly_flags"
    ADD CONSTRAINT "anomaly_flags_public_id_unique" UNIQUE ("public_id");


--
-- Name: anomaly_reviews anomaly_reviews_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."anomaly_reviews"
    ADD CONSTRAINT "anomaly_reviews_pkey" PRIMARY KEY ("id");


--
-- Name: scan_attempts attempt_event_identity_unique; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."scan_attempts"
    ADD CONSTRAINT "attempt_event_identity_unique" UNIQUE ("id", "person_id", "credential_assignment_id", "guard_id", "gate_id", "direction", "source");


--
-- Name: audit_logs audit_logs_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."audit_logs"
    ADD CONSTRAINT "audit_logs_pkey" PRIMARY KEY ("id");


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."cache_locks"
    ADD CONSTRAINT "cache_locks_pkey" PRIMARY KEY ("key");


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."cache"
    ADD CONSTRAINT "cache_pkey" PRIMARY KEY ("key");


--
-- Name: campus_departments campus_departments_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."campus_departments"
    ADD CONSTRAINT "campus_departments_pkey" PRIMARY KEY ("name");


--
-- Name: campus_programs campus_programs_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."campus_programs"
    ADD CONSTRAINT "campus_programs_pkey" PRIMARY KEY ("code");


--
-- Name: credential_assignments credential_assignments_credential_id_unique; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."credential_assignments"
    ADD CONSTRAINT "credential_assignments_credential_id_unique" UNIQUE ("credential_id");


--
-- Name: credential_assignments credential_assignments_person_id_id_unique; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."credential_assignments"
    ADD CONSTRAINT "credential_assignments_person_id_id_unique" UNIQUE ("person_id", "id");


--
-- Name: credential_assignments credential_assignments_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."credential_assignments"
    ADD CONSTRAINT "credential_assignments_pkey" PRIMARY KEY ("id");


--
-- Name: credential_counters credential_counters_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."credential_counters"
    ADD CONSTRAINT "credential_counters_pkey" PRIMARY KEY ("kind");


--
-- Name: employee_profiles employee_profiles_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."employee_profiles"
    ADD CONSTRAINT "employee_profiles_pkey" PRIMARY KEY ("person_id");


--
-- Name: gates gates_code_unique; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."gates"
    ADD CONSTRAINT "gates_code_unique" UNIQUE ("code");


--
-- Name: gates gates_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."gates"
    ADD CONSTRAINT "gates_pkey" PRIMARY KEY ("id");


--
-- Name: guard_gate_assignments guard_gate_assignments_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."guard_gate_assignments"
    ADD CONSTRAINT "guard_gate_assignments_pkey" PRIMARY KEY ("id");


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."migrations"
    ADD CONSTRAINT "migrations_pkey" PRIMARY KEY ("id");


--
-- Name: nfc_credentials nfc_credentials_created_by_issuance_request_id_unique; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."nfc_credentials"
    ADD CONSTRAINT "nfc_credentials_created_by_issuance_request_id_unique" UNIQUE ("created_by", "issuance_request_id");


--
-- Name: nfc_credentials nfc_credentials_label_unique; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."nfc_credentials"
    ADD CONSTRAINT "nfc_credentials_label_unique" UNIQUE ("label");


--
-- Name: nfc_credentials nfc_credentials_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."nfc_credentials"
    ADD CONSTRAINT "nfc_credentials_pkey" PRIMARY KEY ("id");


--
-- Name: nfc_credentials nfc_credentials_public_id_unique; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."nfc_credentials"
    ADD CONSTRAINT "nfc_credentials_public_id_unique" UNIQUE ("public_id");


--
-- Name: nfc_credentials nfc_credentials_token_digest_unique; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."nfc_credentials"
    ADD CONSTRAINT "nfc_credentials_token_digest_unique" UNIQUE ("token_digest");


--
-- Name: passage_corrections passage_corrections_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."passage_corrections"
    ADD CONSTRAINT "passage_corrections_pkey" PRIMARY KEY ("id");


--
-- Name: passage_corrections passage_corrections_public_id_unique; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."passage_corrections"
    ADD CONSTRAINT "passage_corrections_public_id_unique" UNIQUE ("public_id");


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."password_reset_tokens"
    ADD CONSTRAINT "password_reset_tokens_pkey" PRIMARY KEY ("email");


--
-- Name: persons persons_active_credential_assignment_id_unique; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."persons"
    ADD CONSTRAINT "persons_active_credential_assignment_id_unique" UNIQUE ("active_credential_assignment_id");


--
-- Name: persons persons_person_type_campus_number_unique; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."persons"
    ADD CONSTRAINT "persons_person_type_campus_number_unique" UNIQUE ("person_type", "campus_number");


--
-- Name: persons persons_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."persons"
    ADD CONSTRAINT "persons_pkey" PRIMARY KEY ("id");


--
-- Name: scan_attempts scan_attempts_guard_id_request_id_unique; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."scan_attempts"
    ADD CONSTRAINT "scan_attempts_guard_id_request_id_unique" UNIQUE ("guard_id", "request_id");


--
-- Name: scan_attempts scan_attempts_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."scan_attempts"
    ADD CONSTRAINT "scan_attempts_pkey" PRIMARY KEY ("id");


--
-- Name: scan_attempts scan_attempts_public_id_unique; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."scan_attempts"
    ADD CONSTRAINT "scan_attempts_public_id_unique" UNIQUE ("public_id");


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."sessions"
    ADD CONSTRAINT "sessions_pkey" PRIMARY KEY ("id");


--
-- Name: student_profiles student_profiles_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."student_profiles"
    ADD CONSTRAINT "student_profiles_pkey" PRIMARY KEY ("person_id");


--
-- Name: summary_controls summary_controls_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."summary_controls"
    ADD CONSTRAINT "summary_controls_pkey" PRIMARY KEY ("id");


--
-- Name: summary_requests summary_requests_actor_id_request_key_unique; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."summary_requests"
    ADD CONSTRAINT "summary_requests_actor_id_request_key_unique" UNIQUE ("actor_id", "request_key");


--
-- Name: summary_requests summary_requests_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."summary_requests"
    ADD CONSTRAINT "summary_requests_pkey" PRIMARY KEY ("id");


--
-- Name: summary_requests summary_requests_public_id_unique; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."summary_requests"
    ADD CONSTRAINT "summary_requests_public_id_unique" UNIQUE ("public_id");


--
-- Name: summary_results summary_results_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."summary_results"
    ADD CONSTRAINT "summary_results_pkey" PRIMARY KEY ("id");


--
-- Name: summary_results summary_results_summary_request_id_unique; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."summary_results"
    ADD CONSTRAINT "summary_results_summary_request_id_unique" UNIQUE ("summary_request_id");


--
-- Name: summary_reviews summary_reviews_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."summary_reviews"
    ADD CONSTRAINT "summary_reviews_pkey" PRIMARY KEY ("id");


--
-- Name: summary_tasks summary_tasks_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."summary_tasks"
    ADD CONSTRAINT "summary_tasks_pkey" PRIMARY KEY ("id");


--
-- Name: summary_tasks summary_tasks_summary_request_id_unique; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."summary_tasks"
    ADD CONSTRAINT "summary_tasks_summary_request_id_unique" UNIQUE ("summary_request_id");


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."users"
    ADD CONSTRAINT "users_email_unique" UNIQUE ("email");


--
-- Name: users users_gate_id_unique; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."users"
    ADD CONSTRAINT "users_gate_id_unique" UNIQUE ("gate_id");


--
-- Name: users users_person_id_unique; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."users"
    ADD CONSTRAINT "users_person_id_unique" UNIQUE ("person_id");


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."users"
    ADD CONSTRAINT "users_pkey" PRIMARY KEY ("id");


--
-- Name: users users_username_unique; Type: CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."users"
    ADD CONSTRAINT "users_username_unique" UNIQUE ("username");


--
-- Name: access_events_confirmed_at_id_index; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "access_events_confirmed_at_id_index" ON "nfcheck_online"."access_events" USING "btree" ("confirmed_at", "id");


--
-- Name: anomaly_reviews_anomaly_flag_id_id_index; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "anomaly_reviews_anomaly_flag_id_id_index" ON "nfcheck_online"."anomaly_reviews" USING "btree" ("anomaly_flag_id", "id");


--
-- Name: anomaly_source_clock; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "anomaly_source_clock" ON "nfcheck_online"."anomaly_flags" USING "btree" ("source", "occurred_at", "id");


--
-- Name: attempt_source_clock; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "attempt_source_clock" ON "nfcheck_online"."scan_attempts" USING "btree" ("source", "received_at", "id");


--
-- Name: audit_logs_action_index; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "audit_logs_action_index" ON "nfcheck_online"."audit_logs" USING "btree" ("action");


--
-- Name: audit_logs_actor_id_created_at_index; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "audit_logs_actor_id_created_at_index" ON "nfcheck_online"."audit_logs" USING "btree" ("actor_id", "created_at");


--
-- Name: audit_logs_created_at_index; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "audit_logs_created_at_index" ON "nfcheck_online"."audit_logs" USING "btree" ("created_at");


--
-- Name: audit_logs_target_type_target_id_index; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "audit_logs_target_type_target_id_index" ON "nfcheck_online"."audit_logs" USING "btree" ("target_type", "target_id");


--
-- Name: authorizations_effective_index; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "authorizations_effective_index" ON "nfcheck_online"."access_authorizations" USING "btree" ("person_id", "status", "valid_from", "valid_until");


--
-- Name: cache_expiration_index; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "cache_expiration_index" ON "nfcheck_online"."cache" USING "btree" ("expiration");


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "cache_locks_expiration_index" ON "nfcheck_online"."cache_locks" USING "btree" ("expiration");


--
-- Name: correction_decision_lookup; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "correction_decision_lookup" ON "nfcheck_online"."passage_corrections" USING "btree" ("access_event_id", "action", "id");


--
-- Name: credential_assignments_person_id_assigned_at_index; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "credential_assignments_person_id_assigned_at_index" ON "nfcheck_online"."credential_assignments" USING "btree" ("person_id", "assigned_at");


--
-- Name: event_presence_lookup; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "event_presence_lookup" ON "nfcheck_online"."access_events" USING "btree" ("source", "person_id", "confirmed_at", "id");


--
-- Name: event_source_clock; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "event_source_clock" ON "nfcheck_online"."access_events" USING "btree" ("source", "confirmed_at", "id");


--
-- Name: event_source_gate_clock; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "event_source_gate_clock" ON "nfcheck_online"."access_events" USING "btree" ("source", "gate_id", "confirmed_at", "id");


--
-- Name: gates_archived_at_status_index; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "gates_archived_at_status_index" ON "nfcheck_online"."gates" USING "btree" ("archived_at", "status");


--
-- Name: gates_status_index; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "gates_status_index" ON "nfcheck_online"."gates" USING "btree" ("status");


--
-- Name: guard_gate_effective_index; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "guard_gate_effective_index" ON "nfcheck_online"."guard_gate_assignments" USING "btree" ("guard_id", "gate_id", "starts_at", "ends_at");


--
-- Name: nfc_credentials_source_status_created_at_index; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "nfc_credentials_source_status_created_at_index" ON "nfcheck_online"."nfc_credentials" USING "btree" ("source", "status", "created_at");


--
-- Name: nfc_credentials_status_pending_expires_at_index; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "nfc_credentials_status_pending_expires_at_index" ON "nfcheck_online"."nfc_credentials" USING "btree" ("status", "pending_expires_at");


--
-- Name: nfcheck_ci_credential_label; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE UNIQUE INDEX "nfcheck_ci_credential_label" ON "nfcheck_online"."nfc_credentials" USING "btree" ("lower"(("label")::"text"));


--
-- Name: nfcheck_ci_gates_code; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE UNIQUE INDEX "nfcheck_ci_gates_code" ON "nfcheck_online"."gates" USING "btree" ("lower"(("code")::"text"));


--
-- Name: nfcheck_ci_persons_number; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE UNIQUE INDEX "nfcheck_ci_persons_number" ON "nfcheck_online"."persons" USING "btree" ("person_type", "lower"(("campus_number")::"text"));


--
-- Name: nfcheck_ci_users_email; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE UNIQUE INDEX "nfcheck_ci_users_email" ON "nfcheck_online"."users" USING "btree" ("lower"(("email")::"text"));


--
-- Name: nfcheck_ci_users_username; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE UNIQUE INDEX "nfcheck_ci_users_username" ON "nfcheck_online"."users" USING "btree" ("lower"(("username")::"text"));


--
-- Name: passage_duplicate_lookup; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "passage_duplicate_lookup" ON "nfcheck_online"."access_events" USING "btree" ("person_id", "gate_id", "direction", "source", "confirmed_at");


--
-- Name: persons_archived_at_status_index; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "persons_archived_at_status_index" ON "nfcheck_online"."persons" USING "btree" ("archived_at", "status");


--
-- Name: persons_person_type_status_index; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "persons_person_type_status_index" ON "nfcheck_online"."persons" USING "btree" ("person_type", "status");


--
-- Name: scan_attempts_gate_id_received_at_id_index; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "scan_attempts_gate_id_received_at_id_index" ON "nfcheck_online"."scan_attempts" USING "btree" ("gate_id", "received_at", "id");


--
-- Name: scan_attempts_review_status_review_expires_at_index; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "scan_attempts_review_status_review_expires_at_index" ON "nfcheck_online"."scan_attempts" USING "btree" ("review_status", "review_expires_at");


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "sessions_last_activity_index" ON "nfcheck_online"."sessions" USING "btree" ("last_activity");


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "sessions_user_id_index" ON "nfcheck_online"."sessions" USING "btree" ("user_id");


--
-- Name: summary_requests_source_created_at_id_index; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "summary_requests_source_created_at_id_index" ON "nfcheck_online"."summary_requests" USING "btree" ("source", "created_at", "id");


--
-- Name: summary_reviews_summary_request_id_id_index; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "summary_reviews_summary_request_id_id_index" ON "nfcheck_online"."summary_reviews" USING "btree" ("summary_request_id", "id");


--
-- Name: users_archived_at_status_index; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "users_archived_at_status_index" ON "nfcheck_online"."users" USING "btree" ("archived_at", "status");


--
-- Name: users_role_status_index; Type: INDEX; Schema: nfcheck_online; Owner: -
--

CREATE INDEX "users_role_status_index" ON "nfcheck_online"."users" USING "btree" ("role", "status");


--
-- Name: access_authorizations access_authorizations_granted_by_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."access_authorizations"
    ADD CONSTRAINT "access_authorizations_granted_by_foreign" FOREIGN KEY ("granted_by") REFERENCES "nfcheck_online"."users"("id") ON DELETE RESTRICT;


--
-- Name: access_authorizations access_authorizations_person_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."access_authorizations"
    ADD CONSTRAINT "access_authorizations_person_id_foreign" FOREIGN KEY ("person_id") REFERENCES "nfcheck_online"."persons"("id") ON DELETE RESTRICT;


--
-- Name: access_authorizations access_authorizations_revoked_by_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."access_authorizations"
    ADD CONSTRAINT "access_authorizations_revoked_by_foreign" FOREIGN KEY ("revoked_by") REFERENCES "nfcheck_online"."users"("id") ON DELETE RESTRICT;


--
-- Name: access_events access_events_credential_assignment_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."access_events"
    ADD CONSTRAINT "access_events_credential_assignment_id_foreign" FOREIGN KEY ("credential_assignment_id") REFERENCES "nfcheck_online"."credential_assignments"("id") ON DELETE RESTRICT;


--
-- Name: access_events access_events_gate_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."access_events"
    ADD CONSTRAINT "access_events_gate_id_foreign" FOREIGN KEY ("gate_id") REFERENCES "nfcheck_online"."gates"("id") ON DELETE RESTRICT;


--
-- Name: access_events access_events_guard_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."access_events"
    ADD CONSTRAINT "access_events_guard_id_foreign" FOREIGN KEY ("guard_id") REFERENCES "nfcheck_online"."users"("id") ON DELETE RESTRICT;


--
-- Name: access_events access_events_person_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."access_events"
    ADD CONSTRAINT "access_events_person_id_foreign" FOREIGN KEY ("person_id") REFERENCES "nfcheck_online"."persons"("id") ON DELETE RESTRICT;


--
-- Name: access_events access_events_scan_attempt_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."access_events"
    ADD CONSTRAINT "access_events_scan_attempt_id_foreign" FOREIGN KEY ("scan_attempt_id") REFERENCES "nfcheck_online"."scan_attempts"("id") ON DELETE RESTRICT;


--
-- Name: anomaly_evidence anomaly_evidence_anomaly_flag_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."anomaly_evidence"
    ADD CONSTRAINT "anomaly_evidence_anomaly_flag_id_foreign" FOREIGN KEY ("anomaly_flag_id") REFERENCES "nfcheck_online"."anomaly_flags"("id") ON DELETE RESTRICT;


--
-- Name: anomaly_evidence anomaly_evidence_scan_attempt_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."anomaly_evidence"
    ADD CONSTRAINT "anomaly_evidence_scan_attempt_id_foreign" FOREIGN KEY ("scan_attempt_id") REFERENCES "nfcheck_online"."scan_attempts"("id") ON DELETE RESTRICT;


--
-- Name: anomaly_flags anomaly_flags_anchor_attempt_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."anomaly_flags"
    ADD CONSTRAINT "anomaly_flags_anchor_attempt_id_foreign" FOREIGN KEY ("anchor_attempt_id") REFERENCES "nfcheck_online"."scan_attempts"("id") ON DELETE RESTRICT;


--
-- Name: anomaly_reviews anomaly_reviews_actor_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."anomaly_reviews"
    ADD CONSTRAINT "anomaly_reviews_actor_id_foreign" FOREIGN KEY ("actor_id") REFERENCES "nfcheck_online"."users"("id") ON DELETE RESTRICT;


--
-- Name: anomaly_reviews anomaly_reviews_anomaly_flag_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."anomaly_reviews"
    ADD CONSTRAINT "anomaly_reviews_anomaly_flag_id_foreign" FOREIGN KEY ("anomaly_flag_id") REFERENCES "nfcheck_online"."anomaly_flags"("id") ON DELETE RESTRICT;


--
-- Name: audit_logs audit_logs_actor_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."audit_logs"
    ADD CONSTRAINT "audit_logs_actor_id_foreign" FOREIGN KEY ("actor_id") REFERENCES "nfcheck_online"."users"("id") ON DELETE RESTRICT;


--
-- Name: credential_assignments credential_assignments_assigned_by_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."credential_assignments"
    ADD CONSTRAINT "credential_assignments_assigned_by_foreign" FOREIGN KEY ("assigned_by") REFERENCES "nfcheck_online"."users"("id") ON DELETE RESTRICT;


--
-- Name: credential_assignments credential_assignments_credential_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."credential_assignments"
    ADD CONSTRAINT "credential_assignments_credential_id_foreign" FOREIGN KEY ("credential_id") REFERENCES "nfcheck_online"."nfc_credentials"("id") ON DELETE RESTRICT;


--
-- Name: credential_assignments credential_assignments_ended_by_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."credential_assignments"
    ADD CONSTRAINT "credential_assignments_ended_by_foreign" FOREIGN KEY ("ended_by") REFERENCES "nfcheck_online"."users"("id") ON DELETE RESTRICT;


--
-- Name: credential_assignments credential_assignments_person_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."credential_assignments"
    ADD CONSTRAINT "credential_assignments_person_id_foreign" FOREIGN KEY ("person_id") REFERENCES "nfcheck_online"."persons"("id") ON DELETE RESTRICT;


--
-- Name: employee_profiles employee_profiles_person_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."employee_profiles"
    ADD CONSTRAINT "employee_profiles_person_id_foreign" FOREIGN KEY ("person_id") REFERENCES "nfcheck_online"."persons"("id") ON DELETE RESTRICT;


--
-- Name: access_events event_attempt_identity_fk; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."access_events"
    ADD CONSTRAINT "event_attempt_identity_fk" FOREIGN KEY ("scan_attempt_id", "person_id", "credential_assignment_id", "guard_id", "gate_id", "direction", "source") REFERENCES "nfcheck_online"."scan_attempts"("id", "person_id", "credential_assignment_id", "guard_id", "gate_id", "direction", "source") ON DELETE RESTRICT;


--
-- Name: guard_gate_assignments guard_gate_assignments_assigned_by_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."guard_gate_assignments"
    ADD CONSTRAINT "guard_gate_assignments_assigned_by_foreign" FOREIGN KEY ("assigned_by") REFERENCES "nfcheck_online"."users"("id") ON DELETE RESTRICT;


--
-- Name: guard_gate_assignments guard_gate_assignments_gate_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."guard_gate_assignments"
    ADD CONSTRAINT "guard_gate_assignments_gate_id_foreign" FOREIGN KEY ("gate_id") REFERENCES "nfcheck_online"."gates"("id") ON DELETE RESTRICT;


--
-- Name: guard_gate_assignments guard_gate_assignments_guard_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."guard_gate_assignments"
    ADD CONSTRAINT "guard_gate_assignments_guard_id_foreign" FOREIGN KEY ("guard_id") REFERENCES "nfcheck_online"."users"("id") ON DELETE RESTRICT;


--
-- Name: guard_gate_assignments guard_gate_assignments_revoked_by_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."guard_gate_assignments"
    ADD CONSTRAINT "guard_gate_assignments_revoked_by_foreign" FOREIGN KEY ("revoked_by") REFERENCES "nfcheck_online"."users"("id") ON DELETE RESTRICT;


--
-- Name: nfc_credentials nfc_credentials_closed_by_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."nfc_credentials"
    ADD CONSTRAINT "nfc_credentials_closed_by_foreign" FOREIGN KEY ("closed_by") REFERENCES "nfcheck_online"."users"("id") ON DELETE RESTRICT;


--
-- Name: nfc_credentials nfc_credentials_created_by_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."nfc_credentials"
    ADD CONSTRAINT "nfc_credentials_created_by_foreign" FOREIGN KEY ("created_by") REFERENCES "nfcheck_online"."users"("id") ON DELETE RESTRICT;


--
-- Name: nfc_credentials nfc_credentials_replaces_credential_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."nfc_credentials"
    ADD CONSTRAINT "nfc_credentials_replaces_credential_id_foreign" FOREIGN KEY ("replaces_credential_id") REFERENCES "nfcheck_online"."nfc_credentials"("id") ON DELETE RESTRICT;


--
-- Name: passage_corrections passage_corrections_access_event_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."passage_corrections"
    ADD CONSTRAINT "passage_corrections_access_event_id_foreign" FOREIGN KEY ("access_event_id") REFERENCES "nfcheck_online"."access_events"("id") ON DELETE RESTRICT;


--
-- Name: passage_corrections passage_corrections_actor_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."passage_corrections"
    ADD CONSTRAINT "passage_corrections_actor_id_foreign" FOREIGN KEY ("actor_id") REFERENCES "nfcheck_online"."users"("id") ON DELETE RESTRICT;


--
-- Name: persons person_active_credential_owner_fk; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."persons"
    ADD CONSTRAINT "person_active_credential_owner_fk" FOREIGN KEY ("id", "active_credential_assignment_id") REFERENCES "nfcheck_online"."credential_assignments"("person_id", "id") ON DELETE RESTRICT;


--
-- Name: scan_attempts scan_attempts_authorization_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."scan_attempts"
    ADD CONSTRAINT "scan_attempts_authorization_id_foreign" FOREIGN KEY ("authorization_id") REFERENCES "nfcheck_online"."access_authorizations"("id") ON DELETE RESTRICT;


--
-- Name: scan_attempts scan_attempts_credential_assignment_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."scan_attempts"
    ADD CONSTRAINT "scan_attempts_credential_assignment_id_foreign" FOREIGN KEY ("credential_assignment_id") REFERENCES "nfcheck_online"."credential_assignments"("id") ON DELETE RESTRICT;


--
-- Name: scan_attempts scan_attempts_credential_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."scan_attempts"
    ADD CONSTRAINT "scan_attempts_credential_id_foreign" FOREIGN KEY ("credential_id") REFERENCES "nfcheck_online"."nfc_credentials"("id") ON DELETE RESTRICT;


--
-- Name: scan_attempts scan_attempts_duplicate_of_event_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."scan_attempts"
    ADD CONSTRAINT "scan_attempts_duplicate_of_event_id_foreign" FOREIGN KEY ("duplicate_of_event_id") REFERENCES "nfcheck_online"."access_events"("id") ON DELETE RESTRICT;


--
-- Name: scan_attempts scan_attempts_gate_assignment_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."scan_attempts"
    ADD CONSTRAINT "scan_attempts_gate_assignment_id_foreign" FOREIGN KEY ("gate_assignment_id") REFERENCES "nfcheck_online"."guard_gate_assignments"("id") ON DELETE RESTRICT;


--
-- Name: scan_attempts scan_attempts_gate_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."scan_attempts"
    ADD CONSTRAINT "scan_attempts_gate_id_foreign" FOREIGN KEY ("gate_id") REFERENCES "nfcheck_online"."gates"("id") ON DELETE RESTRICT;


--
-- Name: scan_attempts scan_attempts_guard_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."scan_attempts"
    ADD CONSTRAINT "scan_attempts_guard_id_foreign" FOREIGN KEY ("guard_id") REFERENCES "nfcheck_online"."users"("id") ON DELETE RESTRICT;


--
-- Name: scan_attempts scan_attempts_person_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."scan_attempts"
    ADD CONSTRAINT "scan_attempts_person_id_foreign" FOREIGN KEY ("person_id") REFERENCES "nfcheck_online"."persons"("id") ON DELETE RESTRICT;


--
-- Name: scan_attempts scan_attempts_reviewed_by_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."scan_attempts"
    ADD CONSTRAINT "scan_attempts_reviewed_by_foreign" FOREIGN KEY ("reviewed_by") REFERENCES "nfcheck_online"."users"("id") ON DELETE RESTRICT;


--
-- Name: sessions sessions_user_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."sessions"
    ADD CONSTRAINT "sessions_user_id_foreign" FOREIGN KEY ("user_id") REFERENCES "nfcheck_online"."users"("id") ON DELETE CASCADE;


--
-- Name: student_profiles student_profiles_person_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."student_profiles"
    ADD CONSTRAINT "student_profiles_person_id_foreign" FOREIGN KEY ("person_id") REFERENCES "nfcheck_online"."persons"("id") ON DELETE RESTRICT;


--
-- Name: summary_requests summary_requests_actor_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."summary_requests"
    ADD CONSTRAINT "summary_requests_actor_id_foreign" FOREIGN KEY ("actor_id") REFERENCES "nfcheck_online"."users"("id") ON DELETE RESTRICT;


--
-- Name: summary_results summary_results_summary_request_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."summary_results"
    ADD CONSTRAINT "summary_results_summary_request_id_foreign" FOREIGN KEY ("summary_request_id") REFERENCES "nfcheck_online"."summary_requests"("id") ON DELETE RESTRICT;


--
-- Name: summary_reviews summary_reviews_actor_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."summary_reviews"
    ADD CONSTRAINT "summary_reviews_actor_id_foreign" FOREIGN KEY ("actor_id") REFERENCES "nfcheck_online"."users"("id") ON DELETE RESTRICT;


--
-- Name: summary_reviews summary_reviews_summary_request_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."summary_reviews"
    ADD CONSTRAINT "summary_reviews_summary_request_id_foreign" FOREIGN KEY ("summary_request_id") REFERENCES "nfcheck_online"."summary_requests"("id") ON DELETE RESTRICT;


--
-- Name: summary_tasks summary_tasks_summary_request_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."summary_tasks"
    ADD CONSTRAINT "summary_tasks_summary_request_id_foreign" FOREIGN KEY ("summary_request_id") REFERENCES "nfcheck_online"."summary_requests"("id") ON DELETE RESTRICT;


--
-- Name: users users_gate_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."users"
    ADD CONSTRAINT "users_gate_id_foreign" FOREIGN KEY ("gate_id") REFERENCES "nfcheck_online"."gates"("id") ON DELETE RESTRICT;


--
-- Name: users users_person_id_foreign; Type: FK CONSTRAINT; Schema: nfcheck_online; Owner: -
--

ALTER TABLE ONLY "nfcheck_online"."users"
    ADD CONSTRAINT "users_person_id_foreign" FOREIGN KEY ("person_id") REFERENCES "nfcheck_online"."persons"("id") ON DELETE RESTRICT;


--
-- PostgreSQL database dump complete
--
