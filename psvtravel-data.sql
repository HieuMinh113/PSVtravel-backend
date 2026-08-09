--
-- PostgreSQL database dump
--

\restrict JwzN3RwrwT2bf3vDQ3OsDh8pmzSF6nWTaZ1k1nFUva7gE8jqsEKwNnsyJer4j8e

-- Dumped from database version 16.14
-- Dumped by pg_dump version 16.14

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

ALTER TABLE IF EXISTS ONLY public.tour_itineraries DROP CONSTRAINT IF EXISTS tour_itineraries_tour_id_foreign;
ALTER TABLE IF EXISTS ONLY public.tour_images DROP CONSTRAINT IF EXISTS tour_images_tour_id_foreign;
ALTER TABLE IF EXISTS ONLY public.tour_departures DROP CONSTRAINT IF EXISTS tour_departures_tour_id_foreign;
ALTER TABLE IF EXISTS ONLY public.role_has_permissions DROP CONSTRAINT IF EXISTS role_has_permissions_role_id_foreign;
ALTER TABLE IF EXISTS ONLY public.role_has_permissions DROP CONSTRAINT IF EXISTS role_has_permissions_permission_id_foreign;
ALTER TABLE IF EXISTS ONLY public.reviews DROP CONSTRAINT IF EXISTS reviews_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.reviews DROP CONSTRAINT IF EXISTS reviews_tour_id_foreign;
ALTER TABLE IF EXISTS ONLY public.reviews DROP CONSTRAINT IF EXISTS reviews_approved_by_foreign;
ALTER TABLE IF EXISTS ONLY public.payments DROP CONSTRAINT IF EXISTS payments_booking_id_foreign;
ALTER TABLE IF EXISTS ONLY public.passkeys DROP CONSTRAINT IF EXISTS passkeys_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.moments DROP CONSTRAINT IF EXISTS moments_tour_id_foreign;
ALTER TABLE IF EXISTS ONLY public.model_has_roles DROP CONSTRAINT IF EXISTS model_has_roles_role_id_foreign;
ALTER TABLE IF EXISTS ONLY public.model_has_permissions DROP CONSTRAINT IF EXISTS model_has_permissions_permission_id_foreign;
ALTER TABLE IF EXISTS ONLY public.guides DROP CONSTRAINT IF EXISTS guides_author_id_foreign;
ALTER TABLE IF EXISTS ONLY public.flight_deals DROP CONSTRAINT IF EXISTS flight_deals_airline_id_foreign;
ALTER TABLE IF EXISTS ONLY public.category_tour DROP CONSTRAINT IF EXISTS category_tour_tour_id_foreign;
ALTER TABLE IF EXISTS ONLY public.category_tour DROP CONSTRAINT IF EXISTS category_tour_category_id_foreign;
ALTER TABLE IF EXISTS ONLY public.bookings DROP CONSTRAINT IF EXISTS bookings_user_id_foreign;
ALTER TABLE IF EXISTS ONLY public.bookings DROP CONSTRAINT IF EXISTS bookings_tour_id_foreign;
ALTER TABLE IF EXISTS ONLY public.bookings DROP CONSTRAINT IF EXISTS bookings_tour_departure_id_foreign;
ALTER TABLE IF EXISTS ONLY public.bookings DROP CONSTRAINT IF EXISTS bookings_cancelled_by_foreign;
DROP INDEX IF EXISTS public.subject;
DROP INDEX IF EXISTS public.sessions_user_id_index;
DROP INDEX IF EXISTS public.sessions_last_activity_index;
DROP INDEX IF EXISTS public.personal_access_tokens_tokenable_type_tokenable_id_index;
DROP INDEX IF EXISTS public.personal_access_tokens_expires_at_index;
DROP INDEX IF EXISTS public.passkeys_user_id_index;
DROP INDEX IF EXISTS public.model_has_roles_model_id_model_type_index;
DROP INDEX IF EXISTS public.model_has_permissions_model_id_model_type_index;
DROP INDEX IF EXISTS public.jobs_queue_index;
DROP INDEX IF EXISTS public.failed_jobs_connection_queue_failed_at_index;
DROP INDEX IF EXISTS public.causer;
DROP INDEX IF EXISTS public.cache_locks_expiration_index;
DROP INDEX IF EXISTS public.cache_expiration_index;
DROP INDEX IF EXISTS public.activity_log_log_name_index;
ALTER TABLE IF EXISTS ONLY public.visa_providers DROP CONSTRAINT IF EXISTS visa_providers_pkey;
ALTER TABLE IF EXISTS ONLY public.visa_countries DROP CONSTRAINT IF EXISTS visa_countries_slug_unique;
ALTER TABLE IF EXISTS ONLY public.visa_countries DROP CONSTRAINT IF EXISTS visa_countries_pkey;
ALTER TABLE IF EXISTS ONLY public.users DROP CONSTRAINT IF EXISTS users_pkey;
ALTER TABLE IF EXISTS ONLY public.users DROP CONSTRAINT IF EXISTS users_google_id_unique;
ALTER TABLE IF EXISTS ONLY public.users DROP CONSTRAINT IF EXISTS users_email_unique;
ALTER TABLE IF EXISTS ONLY public.tours DROP CONSTRAINT IF EXISTS tours_slug_unique;
ALTER TABLE IF EXISTS ONLY public.tours DROP CONSTRAINT IF EXISTS tours_pkey;
ALTER TABLE IF EXISTS ONLY public.tour_itineraries DROP CONSTRAINT IF EXISTS tour_itineraries_pkey;
ALTER TABLE IF EXISTS ONLY public.tour_images DROP CONSTRAINT IF EXISTS tour_images_pkey;
ALTER TABLE IF EXISTS ONLY public.tour_departures DROP CONSTRAINT IF EXISTS tour_departures_tour_id_start_date_unique;
ALTER TABLE IF EXISTS ONLY public.tour_departures DROP CONSTRAINT IF EXISTS tour_departures_pkey;
ALTER TABLE IF EXISTS ONLY public.settings DROP CONSTRAINT IF EXISTS settings_pkey;
ALTER TABLE IF EXISTS ONLY public.settings DROP CONSTRAINT IF EXISTS settings_key_unique;
ALTER TABLE IF EXISTS ONLY public.sessions DROP CONSTRAINT IF EXISTS sessions_pkey;
ALTER TABLE IF EXISTS ONLY public.roles DROP CONSTRAINT IF EXISTS roles_pkey;
ALTER TABLE IF EXISTS ONLY public.roles DROP CONSTRAINT IF EXISTS roles_name_guard_name_unique;
ALTER TABLE IF EXISTS ONLY public.role_has_permissions DROP CONSTRAINT IF EXISTS role_has_permissions_pkey;
ALTER TABLE IF EXISTS ONLY public.reviews DROP CONSTRAINT IF EXISTS reviews_pkey;
ALTER TABLE IF EXISTS ONLY public.personal_access_tokens DROP CONSTRAINT IF EXISTS personal_access_tokens_token_unique;
ALTER TABLE IF EXISTS ONLY public.personal_access_tokens DROP CONSTRAINT IF EXISTS personal_access_tokens_pkey;
ALTER TABLE IF EXISTS ONLY public.permissions DROP CONSTRAINT IF EXISTS permissions_pkey;
ALTER TABLE IF EXISTS ONLY public.permissions DROP CONSTRAINT IF EXISTS permissions_name_guard_name_unique;
ALTER TABLE IF EXISTS ONLY public.payments DROP CONSTRAINT IF EXISTS payments_transaction_ref_unique;
ALTER TABLE IF EXISTS ONLY public.payments DROP CONSTRAINT IF EXISTS payments_pkey;
ALTER TABLE IF EXISTS ONLY public.password_reset_tokens DROP CONSTRAINT IF EXISTS password_reset_tokens_pkey;
ALTER TABLE IF EXISTS ONLY public.passkeys DROP CONSTRAINT IF EXISTS passkeys_pkey;
ALTER TABLE IF EXISTS ONLY public.passkeys DROP CONSTRAINT IF EXISTS passkeys_credential_id_unique;
ALTER TABLE IF EXISTS ONLY public.pages DROP CONSTRAINT IF EXISTS pages_slug_unique;
ALTER TABLE IF EXISTS ONLY public.pages DROP CONSTRAINT IF EXISTS pages_pkey;
ALTER TABLE IF EXISTS ONLY public.moments DROP CONSTRAINT IF EXISTS moments_pkey;
ALTER TABLE IF EXISTS ONLY public.model_has_roles DROP CONSTRAINT IF EXISTS model_has_roles_pkey;
ALTER TABLE IF EXISTS ONLY public.model_has_permissions DROP CONSTRAINT IF EXISTS model_has_permissions_pkey;
ALTER TABLE IF EXISTS ONLY public.migrations DROP CONSTRAINT IF EXISTS migrations_pkey;
ALTER TABLE IF EXISTS ONLY public.jobs DROP CONSTRAINT IF EXISTS jobs_pkey;
ALTER TABLE IF EXISTS ONLY public.job_batches DROP CONSTRAINT IF EXISTS job_batches_pkey;
ALTER TABLE IF EXISTS ONLY public.guides DROP CONSTRAINT IF EXISTS guides_slug_unique;
ALTER TABLE IF EXISTS ONLY public.guides DROP CONSTRAINT IF EXISTS guides_pkey;
ALTER TABLE IF EXISTS ONLY public.flight_deals DROP CONSTRAINT IF EXISTS flight_deals_pkey;
ALTER TABLE IF EXISTS ONLY public.failed_jobs DROP CONSTRAINT IF EXISTS failed_jobs_uuid_unique;
ALTER TABLE IF EXISTS ONLY public.failed_jobs DROP CONSTRAINT IF EXISTS failed_jobs_pkey;
ALTER TABLE IF EXISTS ONLY public.category_tour DROP CONSTRAINT IF EXISTS category_tour_pkey;
ALTER TABLE IF EXISTS ONLY public.category_tour DROP CONSTRAINT IF EXISTS category_tour_category_id_tour_id_unique;
ALTER TABLE IF EXISTS ONLY public.categories DROP CONSTRAINT IF EXISTS categories_slug_unique;
ALTER TABLE IF EXISTS ONLY public.categories DROP CONSTRAINT IF EXISTS categories_pkey;
ALTER TABLE IF EXISTS ONLY public.cache DROP CONSTRAINT IF EXISTS cache_pkey;
ALTER TABLE IF EXISTS ONLY public.cache_locks DROP CONSTRAINT IF EXISTS cache_locks_pkey;
ALTER TABLE IF EXISTS ONLY public.bookings DROP CONSTRAINT IF EXISTS bookings_pkey;
ALTER TABLE IF EXISTS ONLY public.bookings DROP CONSTRAINT IF EXISTS bookings_booking_code_unique;
ALTER TABLE IF EXISTS ONLY public.banners DROP CONSTRAINT IF EXISTS banners_pkey;
ALTER TABLE IF EXISTS ONLY public.airlines DROP CONSTRAINT IF EXISTS airlines_pkey;
ALTER TABLE IF EXISTS ONLY public.airlines DROP CONSTRAINT IF EXISTS airlines_code_unique;
ALTER TABLE IF EXISTS ONLY public.activity_log DROP CONSTRAINT IF EXISTS activity_log_pkey;
ALTER TABLE IF EXISTS public.visa_providers ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.visa_countries ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.users ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.tours ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.tour_itineraries ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.tour_images ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.tour_departures ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.settings ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.roles ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.reviews ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.personal_access_tokens ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.permissions ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.payments ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.passkeys ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.pages ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.moments ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.migrations ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.jobs ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.guides ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.flight_deals ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.failed_jobs ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.category_tour ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.categories ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.bookings ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.banners ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.airlines ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.activity_log ALTER COLUMN id DROP DEFAULT;
DROP SEQUENCE IF EXISTS public.visa_providers_id_seq;
DROP TABLE IF EXISTS public.visa_providers;
DROP SEQUENCE IF EXISTS public.visa_countries_id_seq;
DROP TABLE IF EXISTS public.visa_countries;
DROP SEQUENCE IF EXISTS public.users_id_seq;
DROP TABLE IF EXISTS public.users;
DROP SEQUENCE IF EXISTS public.tours_id_seq;
DROP TABLE IF EXISTS public.tours;
DROP SEQUENCE IF EXISTS public.tour_itineraries_id_seq;
DROP TABLE IF EXISTS public.tour_itineraries;
DROP SEQUENCE IF EXISTS public.tour_images_id_seq;
DROP TABLE IF EXISTS public.tour_images;
DROP SEQUENCE IF EXISTS public.tour_departures_id_seq;
DROP TABLE IF EXISTS public.tour_departures;
DROP SEQUENCE IF EXISTS public.settings_id_seq;
DROP TABLE IF EXISTS public.settings;
DROP TABLE IF EXISTS public.sessions;
DROP SEQUENCE IF EXISTS public.roles_id_seq;
DROP TABLE IF EXISTS public.roles;
DROP TABLE IF EXISTS public.role_has_permissions;
DROP SEQUENCE IF EXISTS public.reviews_id_seq;
DROP TABLE IF EXISTS public.reviews;
DROP SEQUENCE IF EXISTS public.personal_access_tokens_id_seq;
DROP TABLE IF EXISTS public.personal_access_tokens;
DROP SEQUENCE IF EXISTS public.permissions_id_seq;
DROP TABLE IF EXISTS public.permissions;
DROP SEQUENCE IF EXISTS public.payments_id_seq;
DROP TABLE IF EXISTS public.payments;
DROP TABLE IF EXISTS public.password_reset_tokens;
DROP SEQUENCE IF EXISTS public.passkeys_id_seq;
DROP TABLE IF EXISTS public.passkeys;
DROP SEQUENCE IF EXISTS public.pages_id_seq;
DROP TABLE IF EXISTS public.pages;
DROP SEQUENCE IF EXISTS public.moments_id_seq;
DROP TABLE IF EXISTS public.moments;
DROP TABLE IF EXISTS public.model_has_roles;
DROP TABLE IF EXISTS public.model_has_permissions;
DROP SEQUENCE IF EXISTS public.migrations_id_seq;
DROP TABLE IF EXISTS public.migrations;
DROP SEQUENCE IF EXISTS public.jobs_id_seq;
DROP TABLE IF EXISTS public.jobs;
DROP TABLE IF EXISTS public.job_batches;
DROP SEQUENCE IF EXISTS public.guides_id_seq;
DROP TABLE IF EXISTS public.guides;
DROP SEQUENCE IF EXISTS public.flight_deals_id_seq;
DROP TABLE IF EXISTS public.flight_deals;
DROP SEQUENCE IF EXISTS public.failed_jobs_id_seq;
DROP TABLE IF EXISTS public.failed_jobs;
DROP SEQUENCE IF EXISTS public.category_tour_id_seq;
DROP TABLE IF EXISTS public.category_tour;
DROP SEQUENCE IF EXISTS public.categories_id_seq;
DROP TABLE IF EXISTS public.categories;
DROP TABLE IF EXISTS public.cache_locks;
DROP TABLE IF EXISTS public.cache;
DROP SEQUENCE IF EXISTS public.bookings_id_seq;
DROP TABLE IF EXISTS public.bookings;
DROP SEQUENCE IF EXISTS public.banners_id_seq;
DROP TABLE IF EXISTS public.banners;
DROP SEQUENCE IF EXISTS public.airlines_id_seq;
DROP TABLE IF EXISTS public.airlines;
DROP SEQUENCE IF EXISTS public.activity_log_id_seq;
DROP TABLE IF EXISTS public.activity_log;
SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: activity_log; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.activity_log (
    id bigint NOT NULL,
    log_name character varying(255),
    description text NOT NULL,
    subject_type character varying(255),
    subject_id bigint,
    event character varying(255),
    causer_type character varying(255),
    causer_id bigint,
    attribute_changes json,
    properties json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.activity_log OWNER TO psvtravel;

--
-- Name: activity_log_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.activity_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.activity_log_id_seq OWNER TO psvtravel;

--
-- Name: activity_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.activity_log_id_seq OWNED BY public.activity_log.id;


--
-- Name: airlines; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.airlines (
    id bigint NOT NULL,
    code character varying(10) NOT NULL,
    name character varying(255) NOT NULL,
    logo character varying(255),
    country character varying(255),
    website character varying(255),
    note text,
    status character varying(255) DEFAULT 'published'::character varying NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.airlines OWNER TO psvtravel;

--
-- Name: airlines_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.airlines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.airlines_id_seq OWNER TO psvtravel;

--
-- Name: airlines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.airlines_id_seq OWNED BY public.airlines.id;


--
-- Name: banners; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.banners (
    id bigint NOT NULL,
    title character varying(255) NOT NULL,
    subtitle character varying(255),
    image character varying(255) NOT NULL,
    image_mobile character varying(255),
    link character varying(255),
    status character varying(255) DEFAULT 'published'::character varying NOT NULL,
    start_at timestamp(0) without time zone,
    end_at timestamp(0) without time zone,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.banners OWNER TO psvtravel;

--
-- Name: banners_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.banners_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.banners_id_seq OWNER TO psvtravel;

--
-- Name: banners_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.banners_id_seq OWNED BY public.banners.id;


--
-- Name: bookings; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.bookings (
    id bigint NOT NULL,
    booking_code character varying(255) NOT NULL,
    tour_id bigint NOT NULL,
    tour_departure_id bigint,
    user_id bigint,
    customer_name character varying(255) NOT NULL,
    customer_phone character varying(255) NOT NULL,
    customer_email character varying(255),
    adults integer DEFAULT 1 NOT NULL,
    children integer DEFAULT 0 NOT NULL,
    unit_price_adult bigint DEFAULT '0'::bigint NOT NULL,
    unit_price_child bigint DEFAULT '0'::bigint NOT NULL,
    total_price bigint DEFAULT '0'::bigint NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    payment_status character varying(255) DEFAULT 'unpaid'::character varying NOT NULL,
    note text,
    admin_note text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    cancelled_by bigint,
    cancel_reason text,
    cancelled_at timestamp(0) without time zone
);


ALTER TABLE public.bookings OWNER TO psvtravel;

--
-- Name: bookings_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.bookings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.bookings_id_seq OWNER TO psvtravel;

--
-- Name: bookings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.bookings_id_seq OWNED BY public.bookings.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration bigint NOT NULL
);


ALTER TABLE public.cache OWNER TO psvtravel;

--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration bigint NOT NULL
);


ALTER TABLE public.cache_locks OWNER TO psvtravel;

--
-- Name: categories; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.categories (
    id bigint NOT NULL,
    type character varying(255) DEFAULT 'domestic'::character varying NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    description text,
    image character varying(255),
    status character varying(255) DEFAULT 'published'::character varying NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.categories OWNER TO psvtravel;

--
-- Name: categories_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.categories_id_seq OWNER TO psvtravel;

--
-- Name: categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.categories_id_seq OWNED BY public.categories.id;


--
-- Name: category_tour; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.category_tour (
    id bigint NOT NULL,
    category_id bigint NOT NULL,
    tour_id bigint NOT NULL
);


ALTER TABLE public.category_tour OWNER TO psvtravel;

--
-- Name: category_tour_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.category_tour_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.category_tour_id_seq OWNER TO psvtravel;

--
-- Name: category_tour_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.category_tour_id_seq OWNED BY public.category_tour.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection character varying(255) NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.failed_jobs OWNER TO psvtravel;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.failed_jobs_id_seq OWNER TO psvtravel;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: flight_deals; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.flight_deals (
    id bigint NOT NULL,
    airline_id bigint NOT NULL,
    from_city character varying(255) NOT NULL,
    to_city character varying(255) NOT NULL,
    trip_type character varying(255) DEFAULT 'one_way'::character varying NOT NULL,
    price bigint NOT NULL,
    old_price bigint,
    valid_from date,
    valid_to date,
    note text,
    status character varying(255) DEFAULT 'published'::character varying NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.flight_deals OWNER TO psvtravel;

--
-- Name: flight_deals_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.flight_deals_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.flight_deals_id_seq OWNER TO psvtravel;

--
-- Name: flight_deals_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.flight_deals_id_seq OWNED BY public.flight_deals.id;


--
-- Name: guides; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.guides (
    id bigint NOT NULL,
    title character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    excerpt text,
    content text,
    cover_image character varying(255),
    author_id bigint,
    category character varying(255),
    view_count integer DEFAULT 0 NOT NULL,
    status character varying(255) DEFAULT 'draft'::character varying NOT NULL,
    published_at timestamp(0) without time zone,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.guides OWNER TO psvtravel;

--
-- Name: guides_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.guides_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.guides_id_seq OWNER TO psvtravel;

--
-- Name: guides_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.guides_id_seq OWNED BY public.guides.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


ALTER TABLE public.job_batches OWNER TO psvtravel;

--
-- Name: jobs; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


ALTER TABLE public.jobs OWNER TO psvtravel;

--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.jobs_id_seq OWNER TO psvtravel;

--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


ALTER TABLE public.migrations OWNER TO psvtravel;

--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.migrations_id_seq OWNER TO psvtravel;

--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: model_has_permissions; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.model_has_permissions (
    permission_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);


ALTER TABLE public.model_has_permissions OWNER TO psvtravel;

--
-- Name: model_has_roles; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.model_has_roles (
    role_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);


ALTER TABLE public.model_has_roles OWNER TO psvtravel;

--
-- Name: moments; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.moments (
    id bigint NOT NULL,
    image character varying(255) NOT NULL,
    caption character varying(255),
    customer_name character varying(255),
    tour_id bigint,
    status character varying(255) DEFAULT 'published'::character varying NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.moments OWNER TO psvtravel;

--
-- Name: moments_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.moments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.moments_id_seq OWNER TO psvtravel;

--
-- Name: moments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.moments_id_seq OWNED BY public.moments.id;


--
-- Name: pages; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.pages (
    id bigint NOT NULL,
    slug character varying(255) NOT NULL,
    title character varying(255) NOT NULL,
    meta_title character varying(255),
    meta_description text,
    hero_image character varying(255),
    body text,
    content json,
    is_system boolean DEFAULT false NOT NULL,
    status character varying(255) DEFAULT 'published'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.pages OWNER TO psvtravel;

--
-- Name: pages_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.pages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pages_id_seq OWNER TO psvtravel;

--
-- Name: pages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.pages_id_seq OWNED BY public.pages.id;


--
-- Name: passkeys; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.passkeys (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    credential_id character varying(255) NOT NULL,
    credential json NOT NULL,
    last_used_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.passkeys OWNER TO psvtravel;

--
-- Name: passkeys_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.passkeys_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.passkeys_id_seq OWNER TO psvtravel;

--
-- Name: passkeys_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.passkeys_id_seq OWNED BY public.passkeys.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


ALTER TABLE public.password_reset_tokens OWNER TO psvtravel;

--
-- Name: payments; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.payments (
    id bigint NOT NULL,
    booking_id bigint NOT NULL,
    gateway character varying(255) DEFAULT 'momo'::character varying NOT NULL,
    amount bigint NOT NULL,
    transaction_ref character varying(255) NOT NULL,
    gateway_txn_id character varying(255),
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    gateway_response json,
    paid_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.payments OWNER TO psvtravel;

--
-- Name: payments_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.payments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.payments_id_seq OWNER TO psvtravel;

--
-- Name: payments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.payments_id_seq OWNED BY public.payments.id;


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.permissions (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.permissions OWNER TO psvtravel;

--
-- Name: permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.permissions_id_seq OWNER TO psvtravel;

--
-- Name: permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.permissions_id_seq OWNED BY public.permissions.id;


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name text NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.personal_access_tokens OWNER TO psvtravel;

--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.personal_access_tokens_id_seq OWNER TO psvtravel;

--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: reviews; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.reviews (
    id bigint NOT NULL,
    tour_id bigint NOT NULL,
    user_id bigint,
    customer_name character varying(255) NOT NULL,
    rating smallint NOT NULL,
    content text,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    approved_by bigint,
    approved_at timestamp(0) without time zone,
    admin_reply text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.reviews OWNER TO psvtravel;

--
-- Name: reviews_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.reviews_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.reviews_id_seq OWNER TO psvtravel;

--
-- Name: reviews_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.reviews_id_seq OWNED BY public.reviews.id;


--
-- Name: role_has_permissions; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.role_has_permissions (
    permission_id bigint NOT NULL,
    role_id bigint NOT NULL
);


ALTER TABLE public.role_has_permissions OWNER TO psvtravel;

--
-- Name: roles; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.roles OWNER TO psvtravel;

--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.roles_id_seq OWNER TO psvtravel;

--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


ALTER TABLE public.sessions OWNER TO psvtravel;

--
-- Name: settings; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.settings (
    id bigint NOT NULL,
    key character varying(255) NOT NULL,
    value text,
    "group" character varying(255) DEFAULT 'general'::character varying NOT NULL,
    label character varying(255) NOT NULL,
    type character varying(255) DEFAULT 'text'::character varying NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.settings OWNER TO psvtravel;

--
-- Name: settings_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.settings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.settings_id_seq OWNER TO psvtravel;

--
-- Name: settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.settings_id_seq OWNED BY public.settings.id;


--
-- Name: tour_departures; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.tour_departures (
    id bigint NOT NULL,
    tour_id bigint NOT NULL,
    start_date date NOT NULL,
    price_override bigint,
    seats_total integer DEFAULT 0 NOT NULL,
    seats_left integer DEFAULT 0 NOT NULL,
    status character varying(255) DEFAULT 'open'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.tour_departures OWNER TO psvtravel;

--
-- Name: tour_departures_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.tour_departures_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tour_departures_id_seq OWNER TO psvtravel;

--
-- Name: tour_departures_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.tour_departures_id_seq OWNED BY public.tour_departures.id;


--
-- Name: tour_images; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.tour_images (
    id bigint NOT NULL,
    tour_id bigint NOT NULL,
    path character varying(255) NOT NULL,
    alt character varying(255),
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.tour_images OWNER TO psvtravel;

--
-- Name: tour_images_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.tour_images_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tour_images_id_seq OWNER TO psvtravel;

--
-- Name: tour_images_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.tour_images_id_seq OWNED BY public.tour_images.id;


--
-- Name: tour_itineraries; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.tour_itineraries (
    id bigint NOT NULL,
    tour_id bigint NOT NULL,
    day_number smallint NOT NULL,
    title character varying(255) NOT NULL,
    description text,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.tour_itineraries OWNER TO psvtravel;

--
-- Name: tour_itineraries_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.tour_itineraries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tour_itineraries_id_seq OWNER TO psvtravel;

--
-- Name: tour_itineraries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.tour_itineraries_id_seq OWNED BY public.tour_itineraries.id;


--
-- Name: tours; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.tours (
    id bigint NOT NULL,
    slug character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    type character varying(255) DEFAULT 'domestic'::character varying NOT NULL,
    region character varying(255),
    country character varying(255),
    duration_days smallint DEFAULT '1'::smallint NOT NULL,
    duration_nights smallint DEFAULT '0'::smallint NOT NULL,
    departure_from character varying(255),
    adult_price bigint DEFAULT '0'::bigint NOT NULL,
    child_price bigint,
    old_price bigint,
    tag character varying(255),
    cover_image character varying(255),
    highlights json,
    included json,
    excluded json,
    cancellation_policy text,
    description text,
    rating numeric(2,1),
    review_count integer DEFAULT 0 NOT NULL,
    status character varying(255) DEFAULT 'draft'::character varying NOT NULL,
    is_featured boolean DEFAULT false NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.tours OWNER TO psvtravel;

--
-- Name: tours_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.tours_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tours_id_seq OWNER TO psvtravel;

--
-- Name: tours_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.tours_id_seq OWNED BY public.tours.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    two_factor_secret text,
    two_factor_recovery_codes text,
    two_factor_confirmed_at timestamp(0) without time zone,
    phone character varying(255),
    avatar character varying(255),
    google_id character varying(255),
    locale character varying(5) DEFAULT 'vi'::character varying NOT NULL,
    loyalty_points integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.users OWNER TO psvtravel;

--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO psvtravel;

--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: visa_countries; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.visa_countries (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    flag_image character varying(255),
    visa_type character varying(255) DEFAULT 'tourist'::character varying NOT NULL,
    price bigint DEFAULT '0'::bigint NOT NULL,
    processing_time character varying(255),
    success_rate smallint,
    required_documents json,
    description text,
    status character varying(255) DEFAULT 'published'::character varying NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.visa_countries OWNER TO psvtravel;

--
-- Name: visa_countries_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.visa_countries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.visa_countries_id_seq OWNER TO psvtravel;

--
-- Name: visa_countries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.visa_countries_id_seq OWNED BY public.visa_countries.id;


--
-- Name: visa_providers; Type: TABLE; Schema: public; Owner: psvtravel
--

CREATE TABLE public.visa_providers (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    contact_person character varying(255),
    phone character varying(255),
    email character varying(255),
    address character varying(255),
    note text,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


ALTER TABLE public.visa_providers OWNER TO psvtravel;

--
-- Name: visa_providers_id_seq; Type: SEQUENCE; Schema: public; Owner: psvtravel
--

CREATE SEQUENCE public.visa_providers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.visa_providers_id_seq OWNER TO psvtravel;

--
-- Name: visa_providers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: psvtravel
--

ALTER SEQUENCE public.visa_providers_id_seq OWNED BY public.visa_providers.id;


--
-- Name: activity_log id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.activity_log ALTER COLUMN id SET DEFAULT nextval('public.activity_log_id_seq'::regclass);


--
-- Name: airlines id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.airlines ALTER COLUMN id SET DEFAULT nextval('public.airlines_id_seq'::regclass);


--
-- Name: banners id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.banners ALTER COLUMN id SET DEFAULT nextval('public.banners_id_seq'::regclass);


--
-- Name: bookings id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.bookings ALTER COLUMN id SET DEFAULT nextval('public.bookings_id_seq'::regclass);


--
-- Name: categories id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.categories ALTER COLUMN id SET DEFAULT nextval('public.categories_id_seq'::regclass);


--
-- Name: category_tour id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.category_tour ALTER COLUMN id SET DEFAULT nextval('public.category_tour_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: flight_deals id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.flight_deals ALTER COLUMN id SET DEFAULT nextval('public.flight_deals_id_seq'::regclass);


--
-- Name: guides id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.guides ALTER COLUMN id SET DEFAULT nextval('public.guides_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: moments id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.moments ALTER COLUMN id SET DEFAULT nextval('public.moments_id_seq'::regclass);


--
-- Name: pages id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.pages ALTER COLUMN id SET DEFAULT nextval('public.pages_id_seq'::regclass);


--
-- Name: passkeys id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.passkeys ALTER COLUMN id SET DEFAULT nextval('public.passkeys_id_seq'::regclass);


--
-- Name: payments id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.payments ALTER COLUMN id SET DEFAULT nextval('public.payments_id_seq'::regclass);


--
-- Name: permissions id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.permissions ALTER COLUMN id SET DEFAULT nextval('public.permissions_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: reviews id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.reviews ALTER COLUMN id SET DEFAULT nextval('public.reviews_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: settings id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.settings ALTER COLUMN id SET DEFAULT nextval('public.settings_id_seq'::regclass);


--
-- Name: tour_departures id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.tour_departures ALTER COLUMN id SET DEFAULT nextval('public.tour_departures_id_seq'::regclass);


--
-- Name: tour_images id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.tour_images ALTER COLUMN id SET DEFAULT nextval('public.tour_images_id_seq'::regclass);


--
-- Name: tour_itineraries id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.tour_itineraries ALTER COLUMN id SET DEFAULT nextval('public.tour_itineraries_id_seq'::regclass);


--
-- Name: tours id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.tours ALTER COLUMN id SET DEFAULT nextval('public.tours_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: visa_countries id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.visa_countries ALTER COLUMN id SET DEFAULT nextval('public.visa_countries_id_seq'::regclass);


--
-- Name: visa_providers id; Type: DEFAULT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.visa_providers ALTER COLUMN id SET DEFAULT nextval('public.visa_providers_id_seq'::regclass);


--
-- Data for Name: activity_log; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.activity_log (id, log_name, description, subject_type, subject_id, event, causer_type, causer_id, attribute_changes, properties, created_at, updated_at) FROM stdin;
1	tour	updated	Modules\\Tour\\Models\\Tour	1	updated	App\\Models\\User	1	{"attributes":{"adult_price":130000,"child_price":1100000},"old":{"adult_price":120000,"child_price":100000}}	[]	2026-08-07 08:41:37	2026-08-07 08:41:37
2	tour_departure	updated	Modules\\Tour\\Models\\TourDeparture	2	updated	App\\Models\\User	1	{"attributes":{"seats_left":10},"old":{"seats_left":0}}	[]	2026-08-07 08:45:00	2026-08-07 08:45:00
3	booking	created	Modules\\Booking\\Models\\Booking	1	created	App\\Models\\User	1	{"attributes":{"status":"pending","payment_status":"unpaid","total_price":0,"tour_departure_id":2,"adults":1,"children":0,"customer_name":"hieu","customer_phone":"123","cancel_reason":null}}	[]	2026-08-07 08:46:59	2026-08-07 08:46:59
4	user	created	App\\Models\\User	5	created	App\\Models\\User	1	{"attributes":{"name":"hieu","email":"hieu@gmail.com","phone":null}}	[]	2026-08-07 08:48:38	2026-08-07 08:48:38
5	tour	updated	Modules\\Tour\\Models\\Tour	1	updated	App\\Models\\User	1	{"attributes":{"adult_price":1330000,"child_price":11100000},"old":{"adult_price":130000,"child_price":1100000}}	[]	2026-08-07 09:04:18	2026-08-07 09:04:18
6	booking	created	Modules\\Booking\\Models\\Booking	2	created	App\\Models\\User	1	{"attributes":{"status":"pending","payment_status":"unpaid","total_price":1,"tour_departure_id":2,"adults":1,"children":1,"customer_name":"Lil Hmm","customer_phone":"0876143711","cancel_reason":null}}	[]	2026-08-07 13:06:36	2026-08-07 13:06:36
7	booking	updated	Modules\\Booking\\Models\\Booking	2	updated	App\\Models\\User	1	{"attributes":{"total_price":2},"old":{"total_price":1}}	[]	2026-08-07 13:07:18	2026-08-07 13:07:18
8	booking	created	Modules\\Booking\\Models\\Booking	3	created	App\\Models\\User	1	{"attributes":{"status":"pending","payment_status":"unpaid","total_price":12430000,"tour_departure_id":2,"adults":1,"children":1,"customer_name":"Lil Hmm","customer_phone":"0876143711","cancel_reason":null}}	[]	2026-08-07 13:11:07	2026-08-07 13:11:07
9	banner	created	Modules\\Banner\\Models\\Banner	1	created	App\\Models\\User	1	{"attributes":{"title":"ti\\u00eau \\u0111\\u1ec1 g\\u00ec \\u0111\\u00f3 ","status":"published","link":null,"start_at":"2026-08-07T00:00:00.000000Z","end_at":"2026-08-10T00:00:00.000000Z","sort_order":1}}	[]	2026-08-07 15:25:40	2026-08-07 15:25:40
10	banner	created	Modules\\Banner\\Models\\Banner	2	created	App\\Models\\User	1	{"attributes":{"title":"l\\u1ea1i l\\u00e0 g\\u00ec \\u0111\\u00f3 ","status":"published","link":null,"start_at":"2026-08-07T00:00:00.000000Z","end_at":null,"sort_order":2}}	[]	2026-08-07 15:29:30	2026-08-07 15:29:30
11	review	created	Modules\\Review\\Models\\Review	1	created	App\\Models\\User	1	{"attributes":{"status":"pending","rating":5,"admin_reply":null,"customer_name":"hieu"}}	[]	2026-08-07 18:13:00	2026-08-07 18:13:00
12	review	created	Modules\\Review\\Models\\Review	2	created	App\\Models\\User	1	{"attributes":{"status":"pending","rating":3,"admin_reply":null,"customer_name":"hieu"}}	[]	2026-08-07 18:14:08	2026-08-07 18:14:08
13	review	updated	Modules\\Review\\Models\\Review	2	updated	App\\Models\\User	1	{"attributes":{"status":"approved"},"old":{"status":"pending"}}	[]	2026-08-07 18:14:56	2026-08-07 18:14:56
14	review	updated	Modules\\Review\\Models\\Review	1	updated	App\\Models\\User	1	{"attributes":{"status":"approved"},"old":{"status":"pending"}}	[]	2026-08-07 18:14:56	2026-08-07 18:14:56
15	category	created	Modules\\Category\\Models\\Category	1	created	App\\Models\\User	1	{"attributes":{"name":"th\\u00e1i lan","slug":"thai-land","type":"domestic","status":"published","sort_order":1}}	[]	2026-08-07 18:17:05	2026-08-07 18:17:05
16	category	created	Modules\\Category\\Models\\Category	2	created	App\\Models\\User	1	{"attributes":{"name":"trung qu\\u1ed1c ","slug":"trung-quoc","type":"abroad","status":"published","sort_order":1}}	[]	2026-08-07 18:18:26	2026-08-07 18:18:26
17	guide	created	Modules\\Guide\\Models\\Guide	1	created	App\\Models\\User	1	{"attributes":{"title":"hehehe thai lan","slug":"hehe-thai-land","status":"published","published_at":"2026-08-08T00:00:00.000000Z","category":"diem-den"}}	[]	2026-08-07 18:20:22	2026-08-07 18:20:22
\.


--
-- Data for Name: airlines; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.airlines (id, code, name, logo, country, website, note, status, sort_order, created_at, updated_at, deleted_at) FROM stdin;
\.


--
-- Data for Name: banners; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.banners (id, title, subtitle, image, image_mobile, link, status, start_at, end_at, sort_order, created_at, updated_at, deleted_at) FROM stdin;
1	tiêu đề gì đó 	\N	banners/01KZED8YJ8RBVWW1V47ESH9MQ8.jpg	banners/01KZED8YMDCV0XK66NNX5701HD.png	\N	published	2026-08-07 00:00:00	2026-08-10 00:00:00	1	2026-08-07 15:25:40	2026-08-07 15:30:28	\N
2	lại là gì đó 	\N	banners/01KZEDFZDBXY7EXGE8457GGEMD.png	banners/01KZEDFZF50JSQY1YQ98M01D2G.png	\N	published	2026-08-07 00:00:00	\N	2	2026-08-07 15:29:30	2026-08-07 15:30:28	\N
\.


--
-- Data for Name: bookings; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.bookings (id, booking_code, tour_id, tour_departure_id, user_id, customer_name, customer_phone, customer_email, adults, children, unit_price_adult, unit_price_child, total_price, status, payment_status, note, admin_note, created_at, updated_at, deleted_at, cancelled_by, cancel_reason, cancelled_at) FROM stdin;
1	PSV-20260807-35JF	1	2	\N	hieu	123	heiu@gmail.com	1	0	0	0	0	pending	unpaid	\N	\N	2026-08-07 08:46:59	2026-08-07 08:46:59	\N	\N	\N	\N
2	PSV-20260807-OT5Q	1	2	\N	Lil Hmm	0876143711	1@gmail.com	1	1	1330000	11100000	2	pending	unpaid	\N	\N	2026-08-07 13:06:36	2026-08-07 13:07:18	\N	\N	\N	\N
3	PSV-20260807-AMOS	1	2	\N	Lil Hmm	0876143711	hieuvadanh@gmail.com	1	1	1330000	11100000	12430000	pending	unpaid	\N	\N	2026-08-07 13:11:07	2026-08-07 13:11:07	\N	\N	\N	\N
\.


--
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.cache (key, value, expiration) FROM stdin;
\.


--
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.cache_locks (key, owner, expiration) FROM stdin;
\.


--
-- Data for Name: categories; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.categories (id, type, name, slug, description, image, status, sort_order, created_at, updated_at, deleted_at) FROM stdin;
1	domestic	thái lan	thai-land	\N	categories/01KZEQ2TA615RSB4CTZFB4V5D8.jpg	published	1	2026-08-07 18:17:05	2026-08-07 18:17:05	\N
2	abroad	trung quốc 	trung-quoc	\N	categories/01KZEQ59PM2JWXBPECRGVGDD1S.png	published	1	2026-08-07 18:18:26	2026-08-07 18:18:26	\N
\.


--
-- Data for Name: category_tour; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.category_tour (id, category_id, tour_id) FROM stdin;
\.


--
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
\.


--
-- Data for Name: flight_deals; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.flight_deals (id, airline_id, from_city, to_city, trip_type, price, old_price, valid_from, valid_to, note, status, sort_order, created_at, updated_at, deleted_at) FROM stdin;
\.


--
-- Data for Name: guides; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.guides (id, title, slug, excerpt, content, cover_image, author_id, category, view_count, status, published_at, sort_order, created_at, updated_at, deleted_at) FROM stdin;
1	hehehe thai lan	hehe-thai-land	1 cái gì đó rất hehe	<p></p>	guides/01KZEQ8TF8MQRFBZXNF368GMT4.png	\N	diem-den	0	published	2026-08-08 00:00:00	0	2026-08-07 18:20:22	2026-08-07 18:20:22	\N
\.


--
-- Data for Name: job_batches; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.job_batches (id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at) FROM stdin;
\.


--
-- Data for Name: jobs; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.jobs (id, queue, payload, attempts, reserved_at, available_at, created_at) FROM stdin;
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2026_07_30_073434_add_two_factor_columns_to_users_table	2
5	2026_07_30_073435_create_passkeys_table	2
6	2026_07_30_073506_create_personal_access_tokens_table	2
7	2026_07_30_073540_create_roles_table	2
8	2026_07_30_073625_add_profile_columns_to_users_table	2
9	2026_07_31_051243_create_tours_table	3
10	2026_07_31_051302_create_tour_departures_table	3
11	2026_07_31_051405_create_tour_itineraries_table	3
12	2026_07_31_051644_create_tour_images_table	3
13	2026_07_31_072602_create_bookings_table	4
14	2026_07_31_072616_create_payments_table	4
15	2026_07_31_094209_add_cancellation_to_bookings_table	5
16	2026_07_31_165318_drop_legacy_roles_table	6
17	2026_07_31_165528_create_permission_tables	7
18	2026_07_31_180457_drop_is_cover_from_tour_images_table	8
19	2026_08_07_074759_add_unique_to_tour_departures_table	9
20	2026_08_07_082238_create_activity_log_table	10
21	2026_08_07_151526_create_banners_table	11
22	2026_08_07_154048_create_categories_table	12
23	2026_08_07_154119_create_category_tour_table	12
24	2026_08_07_154201_create_guides_table	13
25	2026_08_07_154219_create_moments_table	14
26	2026_08_07_154254_create_reviews_table	15
27	2026_08_07_182547_create_airlines_table	16
28	2026_08_07_182602_create_flight_deals_table	16
29	2026_08_07_182719_create_visa_countries_table	17
30	2026_08_07_182735_create_visa_providers_table	17
31	2026_08_09_175311_create_pages_table	18
32	2026_08_09_175330_create_settings_table	18
\.


--
-- Data for Name: model_has_permissions; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.model_has_permissions (permission_id, model_type, model_id) FROM stdin;
\.


--
-- Data for Name: model_has_roles; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.model_has_roles (role_id, model_type, model_id) FROM stdin;
1	App\\Models\\User	1
3	App\\Models\\User	3
3	App\\Models\\User	4
3	App\\Models\\User	5
\.


--
-- Data for Name: moments; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.moments (id, image, caption, customer_name, tour_id, status, sort_order, created_at, updated_at, deleted_at) FROM stdin;
\.


--
-- Data for Name: pages; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.pages (id, slug, title, meta_title, meta_description, hero_image, body, content, is_system, status, created_at, updated_at, deleted_at) FROM stdin;
\.


--
-- Data for Name: passkeys; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.passkeys (id, user_id, name, credential_id, credential, last_used_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: password_reset_tokens; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.password_reset_tokens (email, token, created_at) FROM stdin;
\.


--
-- Data for Name: payments; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.payments (id, booking_id, gateway, amount, transaction_ref, gateway_txn_id, status, gateway_response, paid_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: permissions; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.permissions (id, name, guard_name, created_at, updated_at) FROM stdin;
1	ViewAny:Role	web	2026-07-31 16:57:27	2026-07-31 16:57:27
2	View:Role	web	2026-07-31 16:57:27	2026-07-31 16:57:27
3	Create:Role	web	2026-07-31 16:57:27	2026-07-31 16:57:27
4	Update:Role	web	2026-07-31 16:57:27	2026-07-31 16:57:27
5	Delete:Role	web	2026-07-31 16:57:27	2026-07-31 16:57:27
6	DeleteAny:Role	web	2026-07-31 16:57:27	2026-07-31 16:57:27
7	Restore:Role	web	2026-07-31 16:57:27	2026-07-31 16:57:27
8	ForceDelete:Role	web	2026-07-31 16:57:27	2026-07-31 16:57:27
9	ForceDeleteAny:Role	web	2026-07-31 16:57:27	2026-07-31 16:57:27
10	RestoreAny:Role	web	2026-07-31 16:57:27	2026-07-31 16:57:27
11	Replicate:Role	web	2026-07-31 16:57:27	2026-07-31 16:57:27
12	Reorder:Role	web	2026-07-31 16:57:27	2026-07-31 16:57:27
13	ViewAny:Booking	web	2026-07-31 17:00:13	2026-07-31 17:00:13
14	View:Booking	web	2026-07-31 17:00:13	2026-07-31 17:00:13
15	Create:Booking	web	2026-07-31 17:00:13	2026-07-31 17:00:13
16	Update:Booking	web	2026-07-31 17:00:13	2026-07-31 17:00:13
17	Delete:Booking	web	2026-07-31 17:00:13	2026-07-31 17:00:13
18	DeleteAny:Booking	web	2026-07-31 17:00:13	2026-07-31 17:00:13
19	Restore:Booking	web	2026-07-31 17:00:13	2026-07-31 17:00:13
20	ForceDelete:Booking	web	2026-07-31 17:00:13	2026-07-31 17:00:13
21	ForceDeleteAny:Booking	web	2026-07-31 17:00:13	2026-07-31 17:00:13
22	RestoreAny:Booking	web	2026-07-31 17:00:13	2026-07-31 17:00:13
23	Replicate:Booking	web	2026-07-31 17:00:13	2026-07-31 17:00:13
24	Reorder:Booking	web	2026-07-31 17:00:13	2026-07-31 17:00:13
25	ViewAny:Tour	web	2026-07-31 17:00:14	2026-07-31 17:00:14
26	View:Tour	web	2026-07-31 17:00:14	2026-07-31 17:00:14
27	Create:Tour	web	2026-07-31 17:00:14	2026-07-31 17:00:14
28	Update:Tour	web	2026-07-31 17:00:14	2026-07-31 17:00:14
29	Delete:Tour	web	2026-07-31 17:00:14	2026-07-31 17:00:14
30	DeleteAny:Tour	web	2026-07-31 17:00:14	2026-07-31 17:00:14
31	Restore:Tour	web	2026-07-31 17:00:14	2026-07-31 17:00:14
32	ForceDelete:Tour	web	2026-07-31 17:00:14	2026-07-31 17:00:14
33	ForceDeleteAny:Tour	web	2026-07-31 17:00:14	2026-07-31 17:00:14
34	RestoreAny:Tour	web	2026-07-31 17:00:14	2026-07-31 17:00:14
35	Replicate:Tour	web	2026-07-31 17:00:14	2026-07-31 17:00:14
36	Reorder:Tour	web	2026-07-31 17:00:14	2026-07-31 17:00:14
37	ViewAny:User	web	2026-07-31 17:32:16	2026-07-31 17:32:16
38	View:User	web	2026-07-31 17:32:16	2026-07-31 17:32:16
39	Create:User	web	2026-07-31 17:32:16	2026-07-31 17:32:16
40	Update:User	web	2026-07-31 17:32:16	2026-07-31 17:32:16
41	Delete:User	web	2026-07-31 17:32:16	2026-07-31 17:32:16
42	DeleteAny:User	web	2026-07-31 17:32:16	2026-07-31 17:32:16
43	Restore:User	web	2026-07-31 17:32:16	2026-07-31 17:32:16
44	ForceDelete:User	web	2026-07-31 17:32:16	2026-07-31 17:32:16
45	ForceDeleteAny:User	web	2026-07-31 17:32:16	2026-07-31 17:32:16
46	RestoreAny:User	web	2026-07-31 17:32:16	2026-07-31 17:32:16
47	Replicate:User	web	2026-07-31 17:32:16	2026-07-31 17:32:16
48	Reorder:User	web	2026-07-31 17:32:16	2026-07-31 17:32:16
49	ViewAny:Activity	web	2026-08-07 15:22:08	2026-08-07 15:22:08
50	View:Activity	web	2026-08-07 15:22:08	2026-08-07 15:22:08
51	Create:Activity	web	2026-08-07 15:22:08	2026-08-07 15:22:08
52	Update:Activity	web	2026-08-07 15:22:08	2026-08-07 15:22:08
53	Delete:Activity	web	2026-08-07 15:22:08	2026-08-07 15:22:08
54	DeleteAny:Activity	web	2026-08-07 15:22:08	2026-08-07 15:22:08
55	Restore:Activity	web	2026-08-07 15:22:08	2026-08-07 15:22:08
56	ForceDelete:Activity	web	2026-08-07 15:22:08	2026-08-07 15:22:08
57	ForceDeleteAny:Activity	web	2026-08-07 15:22:08	2026-08-07 15:22:08
58	RestoreAny:Activity	web	2026-08-07 15:22:08	2026-08-07 15:22:08
59	Replicate:Activity	web	2026-08-07 15:22:08	2026-08-07 15:22:08
60	Reorder:Activity	web	2026-08-07 15:22:08	2026-08-07 15:22:08
61	ViewAny:Banner	web	2026-08-07 15:22:08	2026-08-07 15:22:08
62	View:Banner	web	2026-08-07 15:22:08	2026-08-07 15:22:08
63	Create:Banner	web	2026-08-07 15:22:08	2026-08-07 15:22:08
64	Update:Banner	web	2026-08-07 15:22:08	2026-08-07 15:22:08
65	Delete:Banner	web	2026-08-07 15:22:08	2026-08-07 15:22:08
66	DeleteAny:Banner	web	2026-08-07 15:22:08	2026-08-07 15:22:08
67	Restore:Banner	web	2026-08-07 15:22:08	2026-08-07 15:22:08
68	ForceDelete:Banner	web	2026-08-07 15:22:08	2026-08-07 15:22:08
69	ForceDeleteAny:Banner	web	2026-08-07 15:22:08	2026-08-07 15:22:08
70	RestoreAny:Banner	web	2026-08-07 15:22:08	2026-08-07 15:22:08
71	Replicate:Banner	web	2026-08-07 15:22:08	2026-08-07 15:22:08
72	Reorder:Banner	web	2026-08-07 15:22:08	2026-08-07 15:22:08
73	ViewAny:Category	web	2026-08-07 16:05:27	2026-08-07 16:05:27
74	View:Category	web	2026-08-07 16:05:27	2026-08-07 16:05:27
75	Create:Category	web	2026-08-07 16:05:27	2026-08-07 16:05:27
76	Update:Category	web	2026-08-07 16:05:27	2026-08-07 16:05:27
77	Delete:Category	web	2026-08-07 16:05:27	2026-08-07 16:05:27
78	DeleteAny:Category	web	2026-08-07 16:05:27	2026-08-07 16:05:27
79	Restore:Category	web	2026-08-07 16:05:27	2026-08-07 16:05:27
80	ForceDelete:Category	web	2026-08-07 16:05:27	2026-08-07 16:05:27
81	ForceDeleteAny:Category	web	2026-08-07 16:05:27	2026-08-07 16:05:27
82	RestoreAny:Category	web	2026-08-07 16:05:27	2026-08-07 16:05:27
83	Replicate:Category	web	2026-08-07 16:05:27	2026-08-07 16:05:27
84	Reorder:Category	web	2026-08-07 16:05:27	2026-08-07 16:05:27
85	ViewAny:Guide	web	2026-08-07 16:05:27	2026-08-07 16:05:27
86	View:Guide	web	2026-08-07 16:05:27	2026-08-07 16:05:27
87	Create:Guide	web	2026-08-07 16:05:27	2026-08-07 16:05:27
88	Update:Guide	web	2026-08-07 16:05:27	2026-08-07 16:05:27
89	Delete:Guide	web	2026-08-07 16:05:27	2026-08-07 16:05:27
90	DeleteAny:Guide	web	2026-08-07 16:05:27	2026-08-07 16:05:27
91	Restore:Guide	web	2026-08-07 16:05:27	2026-08-07 16:05:27
92	ForceDelete:Guide	web	2026-08-07 16:05:27	2026-08-07 16:05:27
93	ForceDeleteAny:Guide	web	2026-08-07 16:05:27	2026-08-07 16:05:27
94	RestoreAny:Guide	web	2026-08-07 16:05:27	2026-08-07 16:05:27
95	Replicate:Guide	web	2026-08-07 16:05:27	2026-08-07 16:05:27
96	Reorder:Guide	web	2026-08-07 16:05:27	2026-08-07 16:05:27
97	ViewAny:Moment	web	2026-08-07 16:05:28	2026-08-07 16:05:28
98	View:Moment	web	2026-08-07 16:05:28	2026-08-07 16:05:28
99	Create:Moment	web	2026-08-07 16:05:28	2026-08-07 16:05:28
100	Update:Moment	web	2026-08-07 16:05:28	2026-08-07 16:05:28
101	Delete:Moment	web	2026-08-07 16:05:28	2026-08-07 16:05:28
102	DeleteAny:Moment	web	2026-08-07 16:05:28	2026-08-07 16:05:28
103	Restore:Moment	web	2026-08-07 16:05:28	2026-08-07 16:05:28
104	ForceDelete:Moment	web	2026-08-07 16:05:28	2026-08-07 16:05:28
105	ForceDeleteAny:Moment	web	2026-08-07 16:05:28	2026-08-07 16:05:28
106	RestoreAny:Moment	web	2026-08-07 16:05:28	2026-08-07 16:05:28
107	Replicate:Moment	web	2026-08-07 16:05:28	2026-08-07 16:05:28
108	Reorder:Moment	web	2026-08-07 16:05:28	2026-08-07 16:05:28
109	ViewAny:Review	web	2026-08-07 16:05:28	2026-08-07 16:05:28
110	View:Review	web	2026-08-07 16:05:28	2026-08-07 16:05:28
111	Create:Review	web	2026-08-07 16:05:28	2026-08-07 16:05:28
112	Update:Review	web	2026-08-07 16:05:28	2026-08-07 16:05:28
113	Delete:Review	web	2026-08-07 16:05:28	2026-08-07 16:05:28
114	DeleteAny:Review	web	2026-08-07 16:05:28	2026-08-07 16:05:28
115	Restore:Review	web	2026-08-07 16:05:28	2026-08-07 16:05:28
116	ForceDelete:Review	web	2026-08-07 16:05:28	2026-08-07 16:05:28
117	ForceDeleteAny:Review	web	2026-08-07 16:05:28	2026-08-07 16:05:28
118	RestoreAny:Review	web	2026-08-07 16:05:28	2026-08-07 16:05:28
119	Replicate:Review	web	2026-08-07 16:05:28	2026-08-07 16:05:28
120	Reorder:Review	web	2026-08-07 16:05:28	2026-08-07 16:05:28
121	ViewAny:Airline	web	2026-08-07 18:36:41	2026-08-07 18:36:41
122	View:Airline	web	2026-08-07 18:36:41	2026-08-07 18:36:41
123	Create:Airline	web	2026-08-07 18:36:41	2026-08-07 18:36:41
124	Update:Airline	web	2026-08-07 18:36:41	2026-08-07 18:36:41
125	Delete:Airline	web	2026-08-07 18:36:41	2026-08-07 18:36:41
126	DeleteAny:Airline	web	2026-08-07 18:36:41	2026-08-07 18:36:41
127	Restore:Airline	web	2026-08-07 18:36:41	2026-08-07 18:36:41
128	ForceDelete:Airline	web	2026-08-07 18:36:41	2026-08-07 18:36:41
129	ForceDeleteAny:Airline	web	2026-08-07 18:36:41	2026-08-07 18:36:41
130	RestoreAny:Airline	web	2026-08-07 18:36:41	2026-08-07 18:36:41
131	Replicate:Airline	web	2026-08-07 18:36:41	2026-08-07 18:36:41
132	Reorder:Airline	web	2026-08-07 18:36:41	2026-08-07 18:36:41
133	ViewAny:FlightDeal	web	2026-08-07 18:36:42	2026-08-07 18:36:42
134	View:FlightDeal	web	2026-08-07 18:36:42	2026-08-07 18:36:42
135	Create:FlightDeal	web	2026-08-07 18:36:42	2026-08-07 18:36:42
136	Update:FlightDeal	web	2026-08-07 18:36:42	2026-08-07 18:36:42
137	Delete:FlightDeal	web	2026-08-07 18:36:42	2026-08-07 18:36:42
138	DeleteAny:FlightDeal	web	2026-08-07 18:36:42	2026-08-07 18:36:42
139	Restore:FlightDeal	web	2026-08-07 18:36:42	2026-08-07 18:36:42
140	ForceDelete:FlightDeal	web	2026-08-07 18:36:42	2026-08-07 18:36:42
141	ForceDeleteAny:FlightDeal	web	2026-08-07 18:36:42	2026-08-07 18:36:42
142	RestoreAny:FlightDeal	web	2026-08-07 18:36:42	2026-08-07 18:36:42
143	Replicate:FlightDeal	web	2026-08-07 18:36:42	2026-08-07 18:36:42
144	Reorder:FlightDeal	web	2026-08-07 18:36:42	2026-08-07 18:36:42
145	ViewAny:VisaCountry	web	2026-08-07 18:36:43	2026-08-07 18:36:43
146	View:VisaCountry	web	2026-08-07 18:36:43	2026-08-07 18:36:43
147	Create:VisaCountry	web	2026-08-07 18:36:43	2026-08-07 18:36:43
148	Update:VisaCountry	web	2026-08-07 18:36:43	2026-08-07 18:36:43
149	Delete:VisaCountry	web	2026-08-07 18:36:43	2026-08-07 18:36:43
150	DeleteAny:VisaCountry	web	2026-08-07 18:36:43	2026-08-07 18:36:43
151	Restore:VisaCountry	web	2026-08-07 18:36:43	2026-08-07 18:36:43
152	ForceDelete:VisaCountry	web	2026-08-07 18:36:43	2026-08-07 18:36:43
153	ForceDeleteAny:VisaCountry	web	2026-08-07 18:36:43	2026-08-07 18:36:43
154	RestoreAny:VisaCountry	web	2026-08-07 18:36:43	2026-08-07 18:36:43
155	Replicate:VisaCountry	web	2026-08-07 18:36:43	2026-08-07 18:36:43
156	Reorder:VisaCountry	web	2026-08-07 18:36:43	2026-08-07 18:36:43
157	ViewAny:VisaProvider	web	2026-08-07 18:36:43	2026-08-07 18:36:43
158	View:VisaProvider	web	2026-08-07 18:36:43	2026-08-07 18:36:43
159	Create:VisaProvider	web	2026-08-07 18:36:43	2026-08-07 18:36:43
160	Update:VisaProvider	web	2026-08-07 18:36:43	2026-08-07 18:36:43
161	Delete:VisaProvider	web	2026-08-07 18:36:43	2026-08-07 18:36:43
162	DeleteAny:VisaProvider	web	2026-08-07 18:36:43	2026-08-07 18:36:43
163	Restore:VisaProvider	web	2026-08-07 18:36:43	2026-08-07 18:36:43
164	ForceDelete:VisaProvider	web	2026-08-07 18:36:43	2026-08-07 18:36:43
165	ForceDeleteAny:VisaProvider	web	2026-08-07 18:36:43	2026-08-07 18:36:43
166	RestoreAny:VisaProvider	web	2026-08-07 18:36:43	2026-08-07 18:36:43
167	Replicate:VisaProvider	web	2026-08-07 18:36:43	2026-08-07 18:36:43
168	Reorder:VisaProvider	web	2026-08-07 18:36:43	2026-08-07 18:36:43
169	ViewAny:Page	web	2026-08-09 17:57:09	2026-08-09 17:57:09
170	View:Page	web	2026-08-09 17:57:09	2026-08-09 17:57:09
171	Create:Page	web	2026-08-09 17:57:09	2026-08-09 17:57:09
172	Update:Page	web	2026-08-09 17:57:09	2026-08-09 17:57:09
173	Delete:Page	web	2026-08-09 17:57:09	2026-08-09 17:57:09
174	DeleteAny:Page	web	2026-08-09 17:57:09	2026-08-09 17:57:09
175	Restore:Page	web	2026-08-09 17:57:09	2026-08-09 17:57:09
176	ForceDelete:Page	web	2026-08-09 17:57:09	2026-08-09 17:57:09
177	ForceDeleteAny:Page	web	2026-08-09 17:57:09	2026-08-09 17:57:09
178	RestoreAny:Page	web	2026-08-09 17:57:09	2026-08-09 17:57:09
179	Replicate:Page	web	2026-08-09 17:57:09	2026-08-09 17:57:09
180	Reorder:Page	web	2026-08-09 17:57:09	2026-08-09 17:57:09
181	ViewAny:Setting	web	2026-08-09 17:57:09	2026-08-09 17:57:09
182	View:Setting	web	2026-08-09 17:57:09	2026-08-09 17:57:09
183	Create:Setting	web	2026-08-09 17:57:09	2026-08-09 17:57:09
184	Update:Setting	web	2026-08-09 17:57:09	2026-08-09 17:57:09
185	Delete:Setting	web	2026-08-09 17:57:09	2026-08-09 17:57:09
186	DeleteAny:Setting	web	2026-08-09 17:57:09	2026-08-09 17:57:09
187	Restore:Setting	web	2026-08-09 17:57:09	2026-08-09 17:57:09
188	ForceDelete:Setting	web	2026-08-09 17:57:09	2026-08-09 17:57:09
189	ForceDeleteAny:Setting	web	2026-08-09 17:57:09	2026-08-09 17:57:09
190	RestoreAny:Setting	web	2026-08-09 17:57:09	2026-08-09 17:57:09
191	Replicate:Setting	web	2026-08-09 17:57:09	2026-08-09 17:57:09
192	Reorder:Setting	web	2026-08-09 17:57:09	2026-08-09 17:57:09
\.


--
-- Data for Name: personal_access_tokens; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.personal_access_tokens (id, tokenable_type, tokenable_id, name, token, abilities, last_used_at, expires_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: reviews; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.reviews (id, tour_id, user_id, customer_name, rating, content, status, approved_by, approved_at, admin_reply, created_at, updated_at, deleted_at) FROM stdin;
2	1	\N	hieu	3	123123	approved	1	2026-08-07 18:14:56	\N	2026-08-07 18:14:08	2026-08-07 18:14:56	\N
1	1	\N	hieu	5	cái gì đó 	approved	1	2026-08-07 18:14:56	\N	2026-08-07 18:13:00	2026-08-07 18:14:56	\N
\.


--
-- Data for Name: role_has_permissions; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.role_has_permissions (permission_id, role_id) FROM stdin;
1	1
2	1
3	1
4	1
5	1
6	1
7	1
8	1
9	1
10	1
11	1
12	1
13	1
14	1
15	1
16	1
17	1
18	1
19	1
20	1
21	1
22	1
23	1
24	1
25	1
26	1
27	1
28	1
29	1
30	1
31	1
32	1
33	1
34	1
35	1
36	1
25	3
26	3
37	1
38	1
39	1
40	1
41	1
42	1
43	1
44	1
45	1
46	1
47	1
48	1
49	1
50	1
51	1
52	1
53	1
54	1
55	1
56	1
57	1
58	1
59	1
60	1
61	1
62	1
63	1
64	1
65	1
66	1
67	1
68	1
69	1
70	1
71	1
72	1
73	1
74	1
75	1
76	1
77	1
78	1
79	1
80	1
81	1
82	1
83	1
84	1
85	1
86	1
87	1
88	1
89	1
90	1
91	1
92	1
93	1
94	1
95	1
96	1
97	1
98	1
99	1
100	1
101	1
102	1
103	1
104	1
105	1
106	1
107	1
108	1
109	1
110	1
111	1
112	1
113	1
114	1
115	1
116	1
117	1
118	1
119	1
120	1
121	1
122	1
123	1
124	1
125	1
126	1
127	1
128	1
129	1
130	1
131	1
132	1
133	1
134	1
135	1
136	1
137	1
138	1
139	1
140	1
141	1
142	1
143	1
144	1
145	1
146	1
147	1
148	1
149	1
150	1
151	1
152	1
153	1
154	1
155	1
156	1
157	1
158	1
159	1
160	1
161	1
162	1
163	1
164	1
165	1
166	1
167	1
168	1
169	1
170	1
171	1
172	1
173	1
174	1
175	1
176	1
177	1
178	1
179	1
180	1
181	1
182	1
183	1
184	1
185	1
186	1
187	1
188	1
189	1
190	1
191	1
192	1
\.


--
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.roles (id, name, guard_name, created_at, updated_at) FROM stdin;
1	super_admin	web	2026-07-31 16:57:27	2026-07-31 16:57:27
2	admin	web	2026-07-31 17:04:27	2026-07-31 17:04:27
3	staff	web	2026-07-31 17:04:27	2026-07-31 17:04:27
4	customer	web	2026-07-31 17:04:27	2026-07-31 17:04:27
\.


--
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.sessions (id, user_id, ip_address, user_agent, payload, last_activity) FROM stdin;
\.


--
-- Data for Name: settings; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.settings (id, key, value, "group", label, type, sort_order, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: tour_departures; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.tour_departures (id, tour_id, start_date, price_override, seats_total, seats_left, status, created_at, updated_at) FROM stdin;
2	1	2026-10-08	170000	10	10	open	2026-08-07 08:06:09	2026-08-07 08:45:00
\.


--
-- Data for Name: tour_images; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.tour_images (id, tour_id, path, alt, sort_order, created_at, updated_at) FROM stdin;
1	1	tours/gallery/01KZDKM442JH06MY4ZQRPM2MGK.jpg	ảnh nền 	1	2026-08-07 07:57:23	2026-08-07 07:57:23
\.


--
-- Data for Name: tour_itineraries; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.tour_itineraries (id, tour_id, day_number, title, description, sort_order, created_at, updated_at) FROM stdin;
1	1	1	ngày tham quan 	tham quan ngày đi chơi 	1	2026-08-07 07:53:20	2026-08-07 07:53:20
\.


--
-- Data for Name: tours; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.tours (id, slug, name, type, region, country, duration_days, duration_nights, departure_from, adult_price, child_price, old_price, tag, cover_image, highlights, included, excluded, cancellation_policy, description, rating, review_count, status, is_featured, sort_order, created_at, updated_at, deleted_at) FROM stdin;
1	thai-land	thái lan	abroad	đông nam á	thái lan	2	1	Hồ Chí Minh	1330000	11100000	150000	Mới	tours/01KYVBSTA0KY4CVWERPJTSXR9R.jpg	["\\u00e1dasdasd","\\u00e1dasd","\\u00e1dasdas","\\u00e1dadasd"]	["\\u00e1dasd","\\u00e1dasdasd"]	["\\u00e1dasd","\\u00e1dasdasd"]	gì đó 	cũng gì gì 	4.0	2	draft	f	1	2026-07-31 05:54:21	2026-08-07 18:14:56	\N
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.users (id, name, email, email_verified_at, password, remember_token, created_at, updated_at, two_factor_secret, two_factor_recovery_codes, two_factor_confirmed_at, phone, avatar, google_id, locale, loyalty_points) FROM stdin;
1	Quản trị viên PSVTravel	admin@psvtravel.com	2026-07-30 15:26:09	$2y$12$GrLTFbN6DOLn6Jwezk/jtezcrSbWRIORNmrgYP7yFv5I4E8UUzKXi	6qKFBAR9IWuZtRozmSzVM3jfbNPeRxZbLAritd2FwgDhEdaTztYwEZW5CMX4	2026-07-30 15:26:09	2026-07-30 15:26:09	\N	\N	\N	\N	\N	\N	vi	0
4	hehe	hehe@gmail.com	\N	$2y$12$B.NDTmERrH6mxyKgQlDJHO/0GCsVtYD3Eijs6ZqtBpngNrSGrU9sq	\N	2026-07-31 17:37:31	2026-07-31 17:37:31	\N	\N	\N	\N	\N	\N	vi	0
3	NV Test	nv@test.com	\N	$2y$12$maHLV5p8I5m3c4MotQq7y.kY2AJJEqvyIax7iiNdyVrhND/iwtlXa	DO6ZjPvhU1suTAwmTC1o3oqDlINkfwGzU74VVereX50UZYFMsSaDh7OPLyrU	2026-07-31 17:10:17	2026-07-31 17:10:17	\N	\N	\N	\N	\N	\N	vi	0
5	hieu	hieu@gmail.com	\N	$2y$12$EfJ9UGiBxer7pxRPCYfGo.M77iSACOd8x7wdjjhmzBQSj2ASxeQ06	\N	2026-08-07 08:48:38	2026-08-07 08:48:38	\N	\N	\N	\N	\N	\N	vi	0
\.


--
-- Data for Name: visa_countries; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.visa_countries (id, name, slug, flag_image, visa_type, price, processing_time, success_rate, required_documents, description, status, sort_order, created_at, updated_at, deleted_at) FROM stdin;
\.


--
-- Data for Name: visa_providers; Type: TABLE DATA; Schema: public; Owner: psvtravel
--

COPY public.visa_providers (id, name, contact_person, phone, email, address, note, status, created_at, updated_at, deleted_at) FROM stdin;
\.


--
-- Name: activity_log_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.activity_log_id_seq', 17, true);


--
-- Name: airlines_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.airlines_id_seq', 1, false);


--
-- Name: banners_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.banners_id_seq', 2, true);


--
-- Name: bookings_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.bookings_id_seq', 3, true);


--
-- Name: categories_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.categories_id_seq', 2, true);


--
-- Name: category_tour_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.category_tour_id_seq', 1, false);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- Name: flight_deals_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.flight_deals_id_seq', 1, false);


--
-- Name: guides_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.guides_id_seq', 1, true);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.migrations_id_seq', 32, true);


--
-- Name: moments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.moments_id_seq', 1, false);


--
-- Name: pages_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.pages_id_seq', 1, false);


--
-- Name: passkeys_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.passkeys_id_seq', 1, false);


--
-- Name: payments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.payments_id_seq', 1, false);


--
-- Name: permissions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.permissions_id_seq', 192, true);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.personal_access_tokens_id_seq', 1, false);


--
-- Name: reviews_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.reviews_id_seq', 2, true);


--
-- Name: roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.roles_id_seq', 4, true);


--
-- Name: settings_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.settings_id_seq', 1, false);


--
-- Name: tour_departures_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.tour_departures_id_seq', 2, true);


--
-- Name: tour_images_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.tour_images_id_seq', 1, true);


--
-- Name: tour_itineraries_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.tour_itineraries_id_seq', 1, true);


--
-- Name: tours_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.tours_id_seq', 1, true);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.users_id_seq', 5, true);


--
-- Name: visa_countries_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.visa_countries_id_seq', 1, false);


--
-- Name: visa_providers_id_seq; Type: SEQUENCE SET; Schema: public; Owner: psvtravel
--

SELECT pg_catalog.setval('public.visa_providers_id_seq', 1, false);


--
-- Name: activity_log activity_log_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.activity_log
    ADD CONSTRAINT activity_log_pkey PRIMARY KEY (id);


--
-- Name: airlines airlines_code_unique; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.airlines
    ADD CONSTRAINT airlines_code_unique UNIQUE (code);


--
-- Name: airlines airlines_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.airlines
    ADD CONSTRAINT airlines_pkey PRIMARY KEY (id);


--
-- Name: banners banners_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.banners
    ADD CONSTRAINT banners_pkey PRIMARY KEY (id);


--
-- Name: bookings bookings_booking_code_unique; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.bookings
    ADD CONSTRAINT bookings_booking_code_unique UNIQUE (booking_code);


--
-- Name: bookings bookings_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.bookings
    ADD CONSTRAINT bookings_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: categories categories_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_pkey PRIMARY KEY (id);


--
-- Name: categories categories_slug_unique; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_slug_unique UNIQUE (slug);


--
-- Name: category_tour category_tour_category_id_tour_id_unique; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.category_tour
    ADD CONSTRAINT category_tour_category_id_tour_id_unique UNIQUE (category_id, tour_id);


--
-- Name: category_tour category_tour_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.category_tour
    ADD CONSTRAINT category_tour_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: flight_deals flight_deals_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.flight_deals
    ADD CONSTRAINT flight_deals_pkey PRIMARY KEY (id);


--
-- Name: guides guides_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.guides
    ADD CONSTRAINT guides_pkey PRIMARY KEY (id);


--
-- Name: guides guides_slug_unique; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.guides
    ADD CONSTRAINT guides_slug_unique UNIQUE (slug);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: model_has_permissions model_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_pkey PRIMARY KEY (permission_id, model_id, model_type);


--
-- Name: model_has_roles model_has_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_pkey PRIMARY KEY (role_id, model_id, model_type);


--
-- Name: moments moments_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.moments
    ADD CONSTRAINT moments_pkey PRIMARY KEY (id);


--
-- Name: pages pages_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.pages
    ADD CONSTRAINT pages_pkey PRIMARY KEY (id);


--
-- Name: pages pages_slug_unique; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.pages
    ADD CONSTRAINT pages_slug_unique UNIQUE (slug);


--
-- Name: passkeys passkeys_credential_id_unique; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.passkeys
    ADD CONSTRAINT passkeys_credential_id_unique UNIQUE (credential_id);


--
-- Name: passkeys passkeys_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.passkeys
    ADD CONSTRAINT passkeys_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: payments payments_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_pkey PRIMARY KEY (id);


--
-- Name: payments payments_transaction_ref_unique; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_transaction_ref_unique UNIQUE (transaction_ref);


--
-- Name: permissions permissions_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: reviews reviews_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.reviews
    ADD CONSTRAINT reviews_pkey PRIMARY KEY (id);


--
-- Name: role_has_permissions role_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_pkey PRIMARY KEY (permission_id, role_id);


--
-- Name: roles roles_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: settings settings_key_unique; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.settings
    ADD CONSTRAINT settings_key_unique UNIQUE (key);


--
-- Name: settings settings_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.settings
    ADD CONSTRAINT settings_pkey PRIMARY KEY (id);


--
-- Name: tour_departures tour_departures_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.tour_departures
    ADD CONSTRAINT tour_departures_pkey PRIMARY KEY (id);


--
-- Name: tour_departures tour_departures_tour_id_start_date_unique; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.tour_departures
    ADD CONSTRAINT tour_departures_tour_id_start_date_unique UNIQUE (tour_id, start_date);


--
-- Name: tour_images tour_images_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.tour_images
    ADD CONSTRAINT tour_images_pkey PRIMARY KEY (id);


--
-- Name: tour_itineraries tour_itineraries_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.tour_itineraries
    ADD CONSTRAINT tour_itineraries_pkey PRIMARY KEY (id);


--
-- Name: tours tours_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.tours
    ADD CONSTRAINT tours_pkey PRIMARY KEY (id);


--
-- Name: tours tours_slug_unique; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.tours
    ADD CONSTRAINT tours_slug_unique UNIQUE (slug);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_google_id_unique; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_google_id_unique UNIQUE (google_id);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: visa_countries visa_countries_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.visa_countries
    ADD CONSTRAINT visa_countries_pkey PRIMARY KEY (id);


--
-- Name: visa_countries visa_countries_slug_unique; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.visa_countries
    ADD CONSTRAINT visa_countries_slug_unique UNIQUE (slug);


--
-- Name: visa_providers visa_providers_pkey; Type: CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.visa_providers
    ADD CONSTRAINT visa_providers_pkey PRIMARY KEY (id);


--
-- Name: activity_log_log_name_index; Type: INDEX; Schema: public; Owner: psvtravel
--

CREATE INDEX activity_log_log_name_index ON public.activity_log USING btree (log_name);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: psvtravel
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: psvtravel
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: causer; Type: INDEX; Schema: public; Owner: psvtravel
--

CREATE INDEX causer ON public.activity_log USING btree (causer_type, causer_id);


--
-- Name: failed_jobs_connection_queue_failed_at_index; Type: INDEX; Schema: public; Owner: psvtravel
--

CREATE INDEX failed_jobs_connection_queue_failed_at_index ON public.failed_jobs USING btree (connection, queue, failed_at);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: psvtravel
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: model_has_permissions_model_id_model_type_index; Type: INDEX; Schema: public; Owner: psvtravel
--

CREATE INDEX model_has_permissions_model_id_model_type_index ON public.model_has_permissions USING btree (model_id, model_type);


--
-- Name: model_has_roles_model_id_model_type_index; Type: INDEX; Schema: public; Owner: psvtravel
--

CREATE INDEX model_has_roles_model_id_model_type_index ON public.model_has_roles USING btree (model_id, model_type);


--
-- Name: passkeys_user_id_index; Type: INDEX; Schema: public; Owner: psvtravel
--

CREATE INDEX passkeys_user_id_index ON public.passkeys USING btree (user_id);


--
-- Name: personal_access_tokens_expires_at_index; Type: INDEX; Schema: public; Owner: psvtravel
--

CREATE INDEX personal_access_tokens_expires_at_index ON public.personal_access_tokens USING btree (expires_at);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: psvtravel
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: psvtravel
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: psvtravel
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: subject; Type: INDEX; Schema: public; Owner: psvtravel
--

CREATE INDEX subject ON public.activity_log USING btree (subject_type, subject_id);


--
-- Name: bookings bookings_cancelled_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.bookings
    ADD CONSTRAINT bookings_cancelled_by_foreign FOREIGN KEY (cancelled_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: bookings bookings_tour_departure_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.bookings
    ADD CONSTRAINT bookings_tour_departure_id_foreign FOREIGN KEY (tour_departure_id) REFERENCES public.tour_departures(id) ON DELETE SET NULL;


--
-- Name: bookings bookings_tour_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.bookings
    ADD CONSTRAINT bookings_tour_id_foreign FOREIGN KEY (tour_id) REFERENCES public.tours(id) ON DELETE CASCADE;


--
-- Name: bookings bookings_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.bookings
    ADD CONSTRAINT bookings_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: category_tour category_tour_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.category_tour
    ADD CONSTRAINT category_tour_category_id_foreign FOREIGN KEY (category_id) REFERENCES public.categories(id) ON DELETE CASCADE;


--
-- Name: category_tour category_tour_tour_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.category_tour
    ADD CONSTRAINT category_tour_tour_id_foreign FOREIGN KEY (tour_id) REFERENCES public.tours(id) ON DELETE CASCADE;


--
-- Name: flight_deals flight_deals_airline_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.flight_deals
    ADD CONSTRAINT flight_deals_airline_id_foreign FOREIGN KEY (airline_id) REFERENCES public.airlines(id) ON DELETE CASCADE;


--
-- Name: guides guides_author_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.guides
    ADD CONSTRAINT guides_author_id_foreign FOREIGN KEY (author_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: model_has_permissions model_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: model_has_roles model_has_roles_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: moments moments_tour_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.moments
    ADD CONSTRAINT moments_tour_id_foreign FOREIGN KEY (tour_id) REFERENCES public.tours(id) ON DELETE SET NULL;


--
-- Name: passkeys passkeys_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.passkeys
    ADD CONSTRAINT passkeys_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: payments payments_booking_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_booking_id_foreign FOREIGN KEY (booking_id) REFERENCES public.bookings(id) ON DELETE CASCADE;


--
-- Name: reviews reviews_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.reviews
    ADD CONSTRAINT reviews_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: reviews reviews_tour_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.reviews
    ADD CONSTRAINT reviews_tour_id_foreign FOREIGN KEY (tour_id) REFERENCES public.tours(id) ON DELETE CASCADE;


--
-- Name: reviews reviews_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.reviews
    ADD CONSTRAINT reviews_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: role_has_permissions role_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: tour_departures tour_departures_tour_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.tour_departures
    ADD CONSTRAINT tour_departures_tour_id_foreign FOREIGN KEY (tour_id) REFERENCES public.tours(id) ON DELETE CASCADE;


--
-- Name: tour_images tour_images_tour_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.tour_images
    ADD CONSTRAINT tour_images_tour_id_foreign FOREIGN KEY (tour_id) REFERENCES public.tours(id) ON DELETE CASCADE;


--
-- Name: tour_itineraries tour_itineraries_tour_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: psvtravel
--

ALTER TABLE ONLY public.tour_itineraries
    ADD CONSTRAINT tour_itineraries_tour_id_foreign FOREIGN KEY (tour_id) REFERENCES public.tours(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict JwzN3RwrwT2bf3vDQ3OsDh8pmzSF6nWTaZ1k1nFUva7gE8jqsEKwNnsyJer4j8e

